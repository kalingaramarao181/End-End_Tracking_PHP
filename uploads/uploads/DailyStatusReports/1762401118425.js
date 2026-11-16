const express = require('express');

const app = express();



// Orifice + Wear State

let state = {

  diameter: 0.05,

  Cd: 0.62,

  rho: 1000,

  P1: 200000,

  P2: 100000,

  wear: 0,

  temp: 72

};



function simulate() {

  state.wear += 0.0001 + Math.random() * 0.0004;

  state.diameter += state.wear * 0.0001;

  state.P2 += state.wear * 50;

  state.temp += (Math.random() - 0.5) * 0.6;



  const deltaP = state.P1 - state.P2;

  const A = Math.PI * (state.diameter / 2) ** 2;

  state.flowRate = state.Cd * A * Math.sqrt(2 * deltaP / state.rho);

}



// Initialize simulation state
simulate();

// Run simulation every 3 seconds
setInterval(simulate, 3000);



app.get('/sim', (req, res) => {

  res.json({

    flowRate: state.flowRate.toFixed(4),

    temperature: state.temp.toFixed(1),

    wearLevel: state.wear.toFixed(6),

    deltaP: (state.P1 - state.P2).toFixed(0)

  });

});



app.get('/health', (req, res) => res.json({ status: 'healthy' }));



const port = process.env.PORT || 8080;

app.listen(port, () => console.log(`PLC Sim on ${port}`));

