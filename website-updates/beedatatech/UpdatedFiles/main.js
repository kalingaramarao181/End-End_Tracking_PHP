// main.js
const marqueeContent = document.getElementById("marqueeContent");

projects.forEach(project => {
  const item = document.createElement("div");
  item.classList.add("marquee-item");

  item.innerHTML = `
    <img src="${project.logo}" alt="${project.name}" />
    <span>${project.name}</span>
  `;

  marqueeContent.appendChild(item);
});

// Animated counters
function animateCounter(id, target) {
  let count = 0;
  const speed = 10;
  const increment = target / (10000 / speed); // 2 seconds animation
  const el = document.getElementById(id);

  const counter = setInterval(() => {
    count += increment;
    if (count >= target) {
      el.innerText = target;
      clearInterval(counter);
    } else {
      el.innerText = Math.floor(count);
    }
  }, speed);
}

// Example counts
animateCounter("branchesCount", 4);
// The employee total comes from the E2E directory; never show a static fallback.
(function () {
  const count = document.getElementById('employeesCount');
  if (count) count.textContent = '\u2014';
  const style = document.createElement('link');
  style.rel = 'stylesheet'; style.href = '/UpdatedFiles/employee-counter.css?v=20261010';
  document.head.append(style);
  const script = document.createElement('script');
  script.src = '/UpdatedFiles/employee-api.js?v=20261010';
  script.onload = () => window.BeeDataEmployees.initializeCounter();
  script.onerror = () => {
    const item = count?.closest('.counter-item');
    if (!item) return;
    const link = document.createElement('a');
    link.className = 'employee-counter-link'; link.href = '/employees.html';
    link.setAttribute('aria-label', 'View the BeeData employee directory');
    while (item.firstChild) link.append(item.firstChild);
    item.append(link); item.dataset.employeeDirectory = 'true';
  };
  document.head.append(script);
})();
animateCounter("productsCount", 20);
animateCounter("projectsCount", projects.length);



// Render projects dynamically
const projectsGrid = document.getElementById("projectsGrid");

projects.forEach((project, index) => {
  const card = document.createElement("div");
  card.classList.add("project-card");
  card.style.animationDelay = `${index * 0.2}s`;

  card.innerHTML = `
    <img src="${project.logo}" alt="${project.name} Logo" />
    <a target="_blank" href="${project.link}"><h3>${project.name}</h3></a>
    <p>${project.content}</p>
    <button onclick="window.open('${project.document}', '_blank')">Read More</button>
  `;

  projectsGrid.appendChild(card);
});


