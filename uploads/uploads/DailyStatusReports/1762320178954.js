import React, { useState, useEffect, useRef, useCallback } from 'react';
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd';
import './App.css';
import OptionsGraph from './components/OptionsGraph';
import TrendAnalysis from './components/TrendAnalysis';
import ActiveOptionsBanner from './components/ActiveOptionsBanner';
import DominanceChart from './components/DominanceChart';
import VolumeChart from './components/VolumeChart';
import TradeStrategyFinder from './components/TradeStrategyFinder';
import MoneyFlowTracker from './components/MoneyFlowTracker';
import LiveDataConnector from './components/LiveDataConnector';
import { generateOptionsData } from './data/optionsData';

function App() {
  const [selectedStock, setSelectedStock] = useState('AAPL');
  const [data, setData] = useState(generateOptionsData(selectedStock));
  const [isDarkTheme, setIsDarkTheme] = useState(true);
  const [highlightedNodes, setHighlightedNodes] = useState([]);
  const [showActiveOnly, setShowActiveOnly] = useState(false);
  const [inactiveOpacity, setInactiveOpacity] = useState(0.15);
  const [isUsingDummyData, setIsUsingDummyData] = useState(false);
  
  // Section visibility state
  const [sectionVisibility, setSectionVisibility] = useState({
    trend: true,
    banner: true,
    dominance: true,
    graph: true,
    volume: true,
    strategies: true,
    flow: true,
    live: true,
  });

  // Center panel sections order (all sections can be reordered)
  const [centerSectionsOrder, setCenterSectionsOrder] = useState([
    { id: 'banner', type: 'banner', label: '🔥 Active Options' },
    { id: 'dominance', type: 'dominance', label: '📈 Dominance Chart' },
    { id: 'flow', type: 'flow', label: '💸 Money Flow Tracker' },
    { id: 'graph', type: 'graph', label: '🕸️ Options Graph' },
  ]);

      // Graph size state
      const [graphSize, setGraphSize] = useState({ width: null, height: null });
      const [isResizing, setIsResizing] = useState(false);
      const [resizeStart, setResizeStart] = useState({ x: 0, y: 0, width: 0, height: 0 });
      const graphContainerRef = useRef(null);

  const toggleSection = (sectionId) => {
    setSectionVisibility(prev => ({
      ...prev,
      [sectionId]: !prev[sectionId]
    }));
  };

  const handleStockChange = useCallback((event) => {
    const newStock = event.target.value;
    setSelectedStock(newStock);
    setData(generateOptionsData(newStock));
  }, []);

  const handleRefresh = useCallback(() => {
    setData(generateOptionsData(selectedStock));
  }, [selectedStock]);

  const handleLiveDataUpdate = (newData) => {
    if (newData && newData.nodes && newData.links) {
      setData(newData);
    }
  };

  const toggleTheme = () => {
    setIsDarkTheme(!isDarkTheme);
  };


  const handleCenterSectionsDragEnd = (result) => {
    if (!result.destination) {
      return;
    }

    if (result.source.index === result.destination.index) {
      return;
    }

    const items = Array.from(centerSectionsOrder);
    const [reorderedItem] = items.splice(result.source.index, 1);
    items.splice(result.destination.index, 0, reorderedItem);
    
    setCenterSectionsOrder(items);
  };

  // Graph resize handlers
  const handleResizeStart = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    if (graphContainerRef.current) {
      const rect = graphContainerRef.current.getBoundingClientRect();
      setIsResizing(true);
      setResizeStart({
        x: e.clientX,
        y: e.clientY,
        width: rect.width,
        height: rect.height,
      });
    }
  }, []);

  const handleResize = (e) => {
    if (isResizing && graphContainerRef.current) {
      const deltaX = e.clientX - resizeStart.x;
      const deltaY = e.clientY - resizeStart.y;
      
      setGraphSize({
        width: Math.max(600, resizeStart.width + deltaX),
        height: Math.max(400, resizeStart.height + deltaY),
      });
    }
  };

  const handleResizeEnd = () => {
    setIsResizing(false);
  };

  useEffect(() => {
    if (isResizing) {
      document.addEventListener('mousemove', handleResize);
      document.addEventListener('mouseup', handleResizeEnd);
      return () => {
        document.removeEventListener('mousemove', handleResize);
        document.removeEventListener('mouseup', handleResizeEnd);
      };
    }
  }, [isResizing, resizeStart]);


  const getSectionComponent = useCallback((sectionType, sectionId) => {
    switch (sectionType) {
      case 'banner':
        return (
          <ActiveOptionsBanner
            nodes={data.nodes}
            links={data.links}
            isDarkTheme={isDarkTheme}
            onHighlight={setHighlightedNodes}
          />
        );
      case 'dominance':
        return (
          <DominanceChart
            nodes={data.nodes}
            links={data.links}
            isDarkTheme={isDarkTheme}
          />
        );
      case 'flow':
        return (
          <MoneyFlowTracker
            nodes={data.nodes}
            links={data.links}
            isDarkTheme={isDarkTheme}
          />
        );
      case 'graph':
        return (
          <div 
            ref={graphContainerRef}
            className="graph-resizable-container"
            style={{
              width: graphSize.width || '100%',
              height: graphSize.height || 'auto',
              minHeight: '600px',
              position: 'relative',
            }}
          >
            <div 
              className="resize-handle"
              onMouseDown={handleResizeStart}
              title="Drag to resize graph"
            >
              <span>↘️</span>
            </div>
                  <OptionsGraph
                  data={data}
                  isDarkTheme={isDarkTheme}
                  highlightedNodes={highlightedNodes}
                  onHighlightClear={() => setHighlightedNodes([])}
                  showActiveOnly={showActiveOnly}
                  inactiveOpacity={inactiveOpacity}
                  graphSize={graphSize}
                />
          </div>
        );
      default:
        return null;
    }
  }, [data, isDarkTheme, highlightedNodes, graphSize, showActiveOnly, inactiveOpacity, handleResizeStart]);

  // Apply theme class to body and html
  useEffect(() => {
    if (isDarkTheme) {
      document.body.classList.remove('theme-light');
      document.documentElement.classList.remove('theme-light');
    } else {
      document.body.classList.add('theme-light');
      document.documentElement.classList.add('theme-light');
    }
  }, [isDarkTheme]);

  return (
    <div className={`App ${isDarkTheme ? '' : 'theme-light'}`}>
          <header className="App-header">
            {isUsingDummyData && (
              <div className="dummy-data-banner">
                <span className="dummy-data-icon">⚠️</span>
                <span className="dummy-data-text">
                  <strong>DEMO MODE:</strong> Using dummy/synthetic data for demonstration purposes. Live data sources are unavailable.
                </span>
              </div>
            )}
            <div className="header-top">
              <div className="header-title-section">
                <h1>Options Trading Force-Directed Graph</h1>
                <p className="subtitle">Visualize relationships between options contracts</p>
              </div>
              <div className="header-controls">
                <button 
                  className={`active-filter-button ${showActiveOnly ? 'active' : ''}`}
                  onClick={() => setShowActiveOnly(!showActiveOnly)}
                  title="Show only most active options"
                >
                  {showActiveOnly ? '👁️ Show All' : '🔍 Show Active Only'}
                </button>
                {showActiveOnly && (
                  <div className="opacity-slider-container">
                    <label htmlFor="opacity-slider" className="opacity-slider-label">
                      Inactive Opacity: {Math.round(inactiveOpacity * 100)}%
                    </label>
                    <input
                      id="opacity-slider"
                      type="range"
                      min="0"
                      max="100"
                      value={inactiveOpacity * 100}
                      onChange={(e) => setInactiveOpacity(e.target.value / 100)}
                      className="opacity-slider"
                      title="Adjust opacity of inactive nodes"
                    />
                  </div>
                )}
                <div className="theme-toggle-container">
                  <label className="theme-toggle-label">
                    <span className="theme-toggle-text">🌙</span>
                    <input
                      type="checkbox"
                      className="theme-toggle"
                      checked={!isDarkTheme}
                      onChange={toggleTheme}
                    />
                    <span className="theme-toggle-slider"></span>
                    <span className="theme-toggle-text">☀️</span>
                  </label>
                </div>
              </div>
            </div>
          </header>
      <main className="App-main">
        <div className="main-content">
          <div className="left-panel">
            <div className="section-wrapper">
              <div className="section-header-inline">
                <h3 className="section-title-inline">📊 Trend Analysis</h3>
                <button 
                  className="section-toggle-btn"
                  onClick={() => toggleSection('trend')}
                  title={sectionVisibility.trend ? 'Minimize' : 'Maximize'}
                >
                  {sectionVisibility.trend ? '−' : '+'}
                </button>
              </div>
              {sectionVisibility.trend && (
                <TrendAnalysis nodes={data.nodes} isDarkTheme={isDarkTheme} />
              )}
            </div>
          </div>
          <div className="center-panel">            <DragDropContext onDragEnd={handleCenterSectionsDragEnd}>
              <Droppable droppableId="center-sections">
                {(provided) => (
                  <div 
                    {...provided.droppableProps} 
                    ref={provided.innerRef}
                    className="center-sections-droppable"
                  >
                    {centerSectionsOrder.map((section, index) => (
                      <Draggable key={section.id} draggableId={section.id} index={index} isDragDisabled={false}>
                        {(provided, snapshot) => (
                          <div
                            ref={provided.innerRef}
                            {...provided.draggableProps}
                            className={`section-wrapper ${snapshot.isDragging ? 'dragging' : ''}`}
                            style={{
                              ...provided.draggableProps.style,
                            }}
                          >
                            <div className="section-header-inline">
                              <div 
                                className="section-header-left"
                                {...provided.dragHandleProps}
                              >
                                <span className="drag-handle-icon" title="Drag to reorder">⋮⋮</span>
                                <h3 className="section-title-inline">{section.label}</h3>
                              </div>
                              <button 
                                className="section-toggle-btn"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  toggleSection(section.id);
                                }}
                                title={sectionVisibility[section.id] ? 'Minimize' : 'Maximize'}
                              >
                                {sectionVisibility[section.id] ? '−' : '+'}
                              </button>
                            </div>
                            {sectionVisibility[section.id] && (
                              getSectionComponent(section.type, section.id)
                            )}
                          </div>
                        )}
                      </Draggable>
                    ))}
                    {provided.placeholder}
                  </div>
                )}
              </Droppable>
            </DragDropContext>
          </div>
          <div className="right-panel">
            <div className="section-wrapper">
              <div className="section-header-inline">
                <h3 className="section-title-inline">🔴 Live Data</h3>
                <button 
                  className="section-toggle-btn"
                  onClick={() => toggleSection('live')}
                  title={sectionVisibility.live ? 'Minimize' : 'Maximize'}
                >
                  {sectionVisibility.live ? '−' : '+'}
                </button>
              </div>
              {sectionVisibility.live ? (
                <LiveDataConnector
                  onDataUpdate={handleLiveDataUpdate}
                  currentTicker={selectedStock}
                  isDarkTheme={isDarkTheme}
                  onDummyDataChange={setIsUsingDummyData}
                />
              ) : (
                <div style={{ padding: '10px', color: 'var(--text-secondary)', fontSize: '0.85rem' }}>
                  Click the + button to expand Live Data panel
                </div>
              )}
            </div>
            <div className="section-wrapper">
              <div className="section-header-inline">
                <h3 className="section-title-inline">📊 High Volume Options</h3>
                <button 
                  className="section-toggle-btn"
                  onClick={() => toggleSection('volume')}
                  title={sectionVisibility.volume ? 'Minimize' : 'Maximize'}
                >
                  {sectionVisibility.volume ? '−' : '+'}
                </button>
              </div>
              {sectionVisibility.volume && (
                <VolumeChart nodes={data.nodes} isDarkTheme={isDarkTheme} />
              )}
            </div>
            <div className="section-wrapper">
              <div className="section-header-inline">
                <h3 className="section-title-inline">💰 Trade Strategies</h3>
                <button 
                  className="section-toggle-btn"
                  onClick={() => toggleSection('strategies')}
                  title={sectionVisibility.strategies ? 'Minimize' : 'Maximize'}
                >
                  {sectionVisibility.strategies ? '−' : '+'}
                </button>
              </div>
              {sectionVisibility.strategies && (
                <TradeStrategyFinder 
                  nodes={data.nodes} 
                  links={data.links} 
                  isDarkTheme={isDarkTheme} 
                />
              )}
            </div>
          </div>
        </div>
      </main>
      <footer className="App-footer">
        <div className="legend">
          <div className="legend-item">
            <span className="legend-color call"></span>
            <span>Call Options</span>
          </div>
          <div className="legend-item">
            <span className="legend-color put"></span>
            <span>Put Options</span>
          </div>
          <div className="legend-item">
            <span className="legend-info">💡 Drag nodes to interact | Hover for details</span>
          </div>
          <div className="legend-item">
            <span className="legend-info">📊 Color = IV | Size = Volume/Price | Hover for Greeks (Δ, Γ, ν, Θ, ρ)</span>
          </div>
        </div>
      </footer>
    </div>
  );
}

export default App;

