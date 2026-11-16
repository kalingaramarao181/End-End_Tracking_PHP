# main.py - CORRECTED VERSION
import requests
import pandas as pd
import numpy as np
from typing import Annotated, Literal
from typing_extensions import TypedDict

from langgraph.graph import StateGraph, START, END, add_messages
from langgraph.checkpoint.memory import InMemorySaver
from langchain_core.messages import HumanMessage, AIMessage
import ta   # pip install ta

# ------------------------------------------------------------------
# 1. API helper
# ------------------------------------------------------------------
OHLCV_API = "https://ohlca-date-api-331576355022.us-central1.run.app/"

def fetch_ohlcv(symbol: str, interval: str, start_date: str, end_date: str):
    params = {"symbol": symbol, "interval": interval,
              "start_date": start_date, "end_date": end_date}
    r = requests.get(OHLCV_API, params=params, timeout=12)
    r.raise_for_status()
    return r.json()["data"]

# ------------------------------------------------------------------
# 2. State
# ------------------------------------------------------------------
class FinBytesState(TypedDict):
    symbol: str
    interval: str
    start_date: str
    end_date: str
    ohlcv: list[dict]
    indicators: dict
    risk_score: float
    signal_score: float
    final_action: Literal["buy", "sell", "hold", "review"]
    messages: Annotated[list, add_messages]

# ------------------------------------------------------------------
# 3. Subgraph – Data + Indicators
# ------------------------------------------------------------------
def load_data(state: FinBytesState):
    raw = fetch_ohlcv(state["symbol"], state["interval"],
                      state["start_date"], state["end_date"])
    return {"ohlcv": raw}

def compute_indicators(state: FinBytesState):
    # Recreate DataFrame from ohlcv data
    df = pd.DataFrame(state["ohlcv"])
    df["date"] = pd.to_datetime(df["date"])
    df = df.sort_values("date")

    # RSI
    df["rsi"] = ta.momentum.RSIIndicator(df["close"], window=14).rsi()

    # MACD
    macd = ta.trend.MACD(df["close"])
    df["macd"] = macd.macd()
    df["macd_signal"] = macd.macd_signal()

    # Bollinger Bands
    bb = ta.volatility.BollingerBands(df["close"])
    df["bb_upper"] = bb.bollinger_hband()
    df["bb_lower"] = bb.bollinger_lband()
    df["bb_mid"]   = bb.bollinger_mavg()

    latest = df.iloc[-1]

    # Convert numpy types to native Python types for serialization
    def to_python_type(val):
        if pd.isna(val):
            return None
        if isinstance(val, (np.integer, int)):
            return int(val)
        if isinstance(val, (np.floating, float)):
            return float(val)
        return val

    bb_position = (latest["close"] - latest["bb_lower"]) / (latest["bb_upper"] - latest["bb_lower"])
    
    return {
        "indicators": {
            "price": to_python_type(latest["close"]),
            "rsi": to_python_type(latest["rsi"]),
            "macd": to_python_type(latest["macd"]),
            "macd_signal": to_python_type(latest["macd_signal"]),
            "bb_position": to_python_type(bb_position),
            "volume": to_python_type(latest["volume"])
        }
    }

data_graph = StateGraph(FinBytesState)
data_graph.add_node("fetch", load_data)
data_graph.add_node("indicators", compute_indicators)
data_graph.set_entry_point("fetch")
data_graph.add_edge("fetch", "indicators")
data_graph.set_finish_point("indicators")
data_subgraph = data_graph.compile()

# ------------------------------------------------------------------
# 4. Subgraph – Risk (VIX placeholder)
# ------------------------------------------------------------------
def get_vix_risk(state: FinBytesState):
    # Replace with real VIX call if you have it
    try:
        vix_raw = fetch_ohlcv("^VIX", "1d", state["start_date"], state["end_date"])
        if vix_raw and len(vix_raw) > 0:
            vix = pd.DataFrame(vix_raw)["close"].iloc[-1]
            risk_score = min(float(vix) / 30.0, 1.0)  # 0-1 normalized
        else:
            # Default risk if no VIX data available
            risk_score = 0.5
    except (KeyError, IndexError, ValueError, requests.RequestException) as e:
        # Default risk if VIX fetch fails
        risk_score = 0.5
    return {"risk_score": risk_score}

risk_graph = StateGraph(FinBytesState)
risk_graph.add_node("vix", get_vix_risk)
risk_graph.set_entry_point("vix")
risk_graph.set_finish_point("vix")
risk_subgraph = risk_graph.compile()

# ------------------------------------------------------------------
# 5. Main graph – Fusion & Decision
# ------------------------------------------------------------------
def run_data(state: FinBytesState):
    return data_subgraph.invoke(state)

def run_risk(state: FinBytesState):
    return risk_subgraph.invoke(state)

def fuse_signals(state: FinBytesState):
    ind = state["indicators"]
    risk = state["risk_score"]

    # Handle None values with defaults
    rsi = ind.get("rsi") if ind.get("rsi") is not None else 50.0
    macd = ind.get("macd") if ind.get("macd") is not None else 0.0
    macd_signal = ind.get("macd_signal") if ind.get("macd_signal") is not None else 0.0
    bb_position = ind.get("bb_position") if ind.get("bb_position") is not None else 0.5
    volume = ind.get("volume") if ind.get("volume") is not None else 0

    rsi_score   = 1 - (rsi / 100) if rsi > 50 else rsi / 100
    macd_score  = 1 if macd > macd_signal else 0
    bb_score    = bb_position
    vol_factor  = min(volume / 1_000_000, 1.5)

    tech = (rsi_score * 0.4 + macd_score * 0.4 + bb_score * 0.2) * vol_factor
    tech = min(tech, 1.0)

    final = tech * 0.7 + (1 - risk) * 0.3
    return {"signal_score": tech, "risk_score": risk}

def decide(state: FinBytesState):
    s = state["signal_score"]
    r = state["risk_score"]
    if s > 0.75 and r < 0.6:
        return {"final_action": "buy"}
    if s < 0.40 or r > 0.80:
        return {"final_action": "sell"}
    if r > 0.70:
        return {"final_action": "review"}
    return {"final_action": "hold"}

def notify(state: FinBytesState):
    ind = state['indicators']
    rsi_val = ind.get('rsi') if ind.get('rsi') is not None else None
    macd_val = ind.get('macd') if ind.get('macd') is not None else None
    macd_signal_val = ind.get('macd_signal') if ind.get('macd_signal') is not None else None
    
    rsi_str = f"{rsi_val:.1f}" if rsi_val is not None else "N/A"
    macd_str = "Bullish" if (macd_val is not None and macd_signal_val is not None and macd_val > macd_signal_val) else ("Bearish" if (macd_val is not None and macd_signal_val is not None) else "N/A")
    
    msg = (
        f"*{state['symbol']}* analysis\n"
        f"Price: ${ind['price']:.2f}\n"
        f"RSI: {rsi_str}\n"
        f"MACD: {macd_str}\n"
        f"Signal: {state['signal_score']:.2f} | Risk: {state['risk_score']:.2f}\n"
        f"**{state['final_action'].upper()}**"
    )
    return {"messages": [AIMessage(content=msg)]}

# ------------------------------------------------------------------
# Build main graph
# ------------------------------------------------------------------
builder = StateGraph(FinBytesState)
builder.add_node("data", run_data)
builder.add_node("risk", run_risk)
builder.add_node("fuse", fuse_signals)
builder.add_node("decide", decide)
builder.add_node("notify", notify)

builder.set_entry_point("data")
builder.add_edge("data", "risk")
builder.add_edge("risk", "fuse")
builder.add_edge("fuse", "decide")

def route(state: FinBytesState):
    return "review" if state["final_action"] == "review" else "notify"

builder.add_conditional_edges("decide", route,
                              {"review": "review", "notify": "notify"})
builder.add_node("review",
                 lambda s: {"messages": [AIMessage("Review needed – approve? (yes/no)")]})
builder.add_edge("review", END)
builder.add_edge("notify", END)

checkpointer = InMemorySaver()
app = builder.compile(checkpointer=checkpointer)

# ------------------------------------------------------------------
# 6. Backtesting Integration
# ------------------------------------------------------------------
def run_simple_backtest():
    """Simple backtest integration"""
    try:
        # Try to import backtest module
        import os
        if os.path.exists('backtest.py'):
            from backtest import run_comprehensive_backtest
            print("🚀 Starting comprehensive backtest...")
            results = run_comprehensive_backtest(app, fetch_ohlcv, "AAPL", "2023-01-01", "2024-12-31")
            return results
        else:
            print("❌ backtest.py not found. Running single signal test only.")
            return None
    except Exception as e:
        print(f"❌ Backtest failed: {e}")
        return None

# ------------------------------------------------------------------
# 7. Main execution
# ------------------------------------------------------------------
if __name__ == "__main__":
    # Test single signal
    print("🧪 Testing Single Signal...")
    cfg = {"configurable": {"thread_id": "test-001"}}
    init = {
        "symbol": "AAPL", "interval": "1d",
        "start_date": "2024-01-01", "end_date": "2024-12-31",
        "messages": [], "ohlcv": [],
        "indicators": {}, "signal_score": 0.0, "risk_score": 0.0,
        "final_action": "hold"
    }

    for chunk in app.stream(init, cfg, stream_mode="updates", subgraphs=True):
        print(chunk)
    
    # Ask about backtest
    print("\n" + "="*50)
    response = input("Run comprehensive backtest? (y/n): ").lower().strip()
    if response in ['y', 'yes']:
        run_simple_backtest()
    else:
        print("Backtest skipped.")