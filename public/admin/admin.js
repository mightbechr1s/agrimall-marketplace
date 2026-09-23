const $ = id => document.getElementById(id);
const F = "animal_farmer_demo_", A = "animal_admin_";

const get = (key, fallback = []) => {
  try { return JSON.parse(localStorage.getItem(key)) || fallback; }
  catch { return fallback; }
};

const save = (key, data) =>
  localStorage.setItem(key, JSON.stringify(data));

const farmerData = name => get(F + name);
const saveFarmer = (name, data) => save(F + name, data);

const defaultFarmers = [
  { id:1, name:"James Aquino", farm:"Aquino Family Farm", location:"Benguet", produce:"Native Potatoes, Eggplants", date:"September 7, 2026", status:"Pending" },
  { id:2, name:"Anton Sanchez", farm:"Sanchez Organic Farm", location:"Quezon", produce:"Onions, Tomatoes", date:"August 31, 2026", status:"Pending" },
  { id:3, name:"Josh Reyez", farm:"Reyez Fresh Farm", location:"Nueva Ecija", produce:"Sweet Potatoes, Corn", date:"September 15, 2026", status:"Pending" }
];

const defaultUsers = [
  { id:1, name:"Maria Santos", email:"maria@animal.com", role:"Farmer", status:"Active" },
  { id:2, name:"Juan Dela Cruz", email:"juan@animal.com", role:"Buyer", status:"Active" },
  { id:3, name:"James Aquino", email:"james@animal.com", role:"Farmer", status:"Pending" },
  { id:4, name:"Anton Sanchez", email:"anton@animal.com", role:"Farmer", status:"Active" },
  { id:5, name:"Lisa Reyes", email:"lisa@animal.com", role:"Buyer", status:"Suspended" }
];

const defaultLogs = [
  { date:"9/23/26", user:"Admin", activity:"Verified farmer application" },
  { date:"9/23/26", user:"Buyer", activity:"Submitted order request" },
  { date:"9/22/26", user:"Farmer", activity:"Created new produce listing" }
];

let farmers = get(A + "farmers", defaultFarmers);
let users = get(A + "users", defaultUsers);
let listings = farmerData("listings");
let orders = farmerData("requests");
let selectedUser = null;


/* ---------- SHARED ---------- */

const logs = () => get(A + "log", defaultLogs);

function log(activity) {
  const data = logs();
  data.unshift({
    date: new Date().toLocaleDateString(),
    user: "Admin",
    activity
  });
  save(A + "log", data);
}

function tag(status) {
  const type =
    /Pending|Awaiting/i.test(status) ? "pending" :
    /Rejected|Cancelled|Suspended|Disabled/i.test(status) ? "bad" :
    /Preparing|Delivery|Pickup|Hold/i.test(status) ? "blue" : "";

  return `<span class="tag ${type}">${status}</span>`;
}

const empty = (cols, text) =>
  `<tr><td colspan="${cols}" class="empty">${text}</td></tr>`;

const listen = (id, event, fn) =>
  $(id)?.addEventListener(event, fn);


/* ---------- NAVIGATION ---------- */

function nav() {
  const page = location.pathname.split("/").pop();
  const links = [
    ["admin-dashboard.html", "Dashboard"],
    ["verification.html", "Farmer Verification"],
    ["users.html", "Users"],
    ["listings.html", "Listings"],
    ["orders.html", "Orders"],
    ["reports.html", "Reports"]
  ];

  const items = links.map(([href, name]) => {
    const active =
      page === href ||
      (page === "farmer-application.html" && href === "verification.html");

    return `<a class="nav-link ${active ? "active" : ""}" href="${href}">${name}</a>`;
  }).join("");

  document.body.insertAdjacentHTML("afterbegin", `
    <header><nav>
      <a class="brand" href="admin-dashboard.html">AniMAL</a>
      ${items}
      <a class="market" href="../index.html">← Marketplace</a>
    </nav></header>
  `);
}


/* ---------- DASHBOARD ---------- */

function dashboard() {
  if (!$("dashboardStats")) return;

  const stats = [
    ["users", "Total Users", users.length],
    ["farmer", "Pending Farmers", farmers.filter(f => f.status === "Pending").length],
    ["box", "Active Listings", listings.filter(p => !p.adminDisabled).length],
    ["orders", "Total Orders", orders.length]
  ];

  $("dashboardStats").innerHTML = stats.map(([icon, name, value]) => `
    <div class="stat">
      <span class="stat-icon">${icons[icon]}</span>
      <div><small>${name}</small><b>${value}</b></div>
    </div>
  `).join("");

  $("recentActivity").innerHTML = logs().slice(0, 6).map(item => `
    <tr><td>${item.date}</td><td>${item.user}</td><td>${item.activity}</td></tr>
  `).join("");
}


/* ---------- ICONS ---------- */

const svg = path => `
  <svg viewBox="0 0 24 24" aria-hidden="true">
    ${path}
  </svg>
`;

const icons = {
  users: svg(`
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
  `),

  farmer: svg(`
    <circle cx="12" cy="8" r="3"/>
    <path d="M5 21v-2a7 7 0 0 1 14 0v2M7 5h10M9 2h6l2 3H7z"/>
  `),

  box: svg(`
    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
    <path d="M3.3 7 12 12l8.7-5M12 22V12"/>
  `),

  orders: svg(`
    <path d="M6 2h12v20H6zM9 6h6M9 10h6M9 14h4"/>
  `),

  pending: svg(`
    <circle cx="12" cy="12" r="9"/>
    <path d="M12 7v5l3 2"/>
  `),

  verified: svg(`
    <circle cx="12" cy="12" r="9"/>
    <path d="m8 12 3 3 5-6"/>
  `)
};


/* ---------- VERIFICATION ---------- */

function verification() {
  if (!$("farmerRows")) return;

  const pending = farmers.filter(f => f.status === "Pending");
  const verified = farmers.filter(f => f.status === "Verified");

  $("pendingCount").textContent = pending.length;
  $("verifiedCount").textContent = verified.length;
  $("totalCount").textContent = farmers.length;

  addStatIcons([
    ["pendingCount", icons.pending],
    ["verifiedCount", icons.verified],
    ["totalCount", icons.farmer]
  ]);

  $("farmerRows").innerHTML = pending.length
    ? pending.map(f => `
        <tr>
          <td><b>${f.name}</b></td>
          <td>${f.location}</td>
          <td>${tag(f.status)}</td>
          <td><a class="btn" href="farmer-application.html?id=${f.id}">Review</a></td>
        </tr>
      `).join("")
    : empty(4, "No pending applications.");
}

function addStatIcons(items) {
  items.forEach(([id, icon]) => {
    const stat = $(id)?.closest(".stat");
    if (!stat || stat.querySelector(".stat-icon")) return;

    stat.querySelector(".stat-mark")?.remove();
    stat.insertAdjacentHTML("afterbegin",
      `<span class="stat-icon">${icon}</span>`
    );
  });
}


/* ---------- FARMER APPLICATION ---------- */

function application() {
  if (!$("application")) return;

  const id = +new URLSearchParams(location.search).get("id");
  const farmer = farmers.find(f => f.id === id);

  if (!farmer) {
    $("application").innerHTML =
      '<p class="empty">Application not found.</p>';
    return;
  }

  const info = [
    ["Farmer Name", farmer.name],
    ["Main Produce", farmer.produce],
    ["Farm Name", farmer.farm],
    ["Date Applied", farmer.date],
    ["Location", farmer.location]
  ];

  $("application").innerHTML = `
    <div class="info">
      ${info.map(([name, value]) =>
        `<div><small>${name}</small><b>${value}</b></div>`
      ).join("")}
      <div><small>Status</small>${tag(farmer.status)}</div>
    </div>
  `;

  $("verify").onclick = () => {
    if (!confirm(`Verify ${farmer.name} as a farmer?`)) return;

    farmer.status = "Verified";
    save(A + "farmers", farmers);
    log(`Verified farmer: ${farmer.name}`);
    location.href = "verification.html";
  };

  $("reject").onclick = () => openModal("rejectModal");
  $("cancelReject").onclick = () => closeModal("rejectModal");

  $("confirmReject").onclick = () => {
    const reason = $("rejectReason").value.trim();

    if (!reason) {
      $("reasonError").style.display = "block";
      return;
    }

    farmer.status = "Rejected";
    farmer.rejectionReason = reason;

    save(A + "farmers", farmers);
    log(`Rejected ${farmer.name}: ${reason}`);
    location.href = "verification.html";
  };
}


/* ---------- USERS ---------- */

function renderUsers() {
  if (!$("userRows")) return;

  const search = ($("userSearch")?.value || "").toLowerCase();
  const role = $("userRole")?.value || "All";
  const status = $("userStatus")?.value || "All";

  const shown = users.filter(user =>
    (role === "All" || user.role === role) &&
    (status === "All" || user.status === status) &&
    `${user.name} ${user.email}`.toLowerCase().includes(search)
  );

  $("userRows").innerHTML = shown.length
    ? shown.map(user => {
        const suspended = user.status === "Suspended";

        return `
          <tr>
            <td><b>${user.name}</b></td>
            <td>${user.email}</td>
            <td>${user.role}</td>
            <td>${tag(user.status)}</td>
            <td>
              <button class="btn ${suspended ? "light" : "red"}"
                      onclick="userAction(${user.id})">
                ${suspended ? "Reactivate" : "Suspend"}
              </button>
            </td>
          </tr>
        `;
      }).join("")
    : empty(5, "No users found.");
}

function userAction(id) {
  const user = users.find(u => u.id === id);
  if (!user) return;

  if (user.status === "Suspended") {
    if (!confirm(`Reactivate ${user.name}?`)) return;

    user.status = "Active";
    delete user.suspensionReason;

    save(A + "users", users);
    log(`Reactivated user account (${user.name})`);
    renderUsers();
    return;
  }

  selectedUser = user;
  $("suspendName").textContent = user.name;
  $("suspendReason").value = "";
  $("suspendError").style.display = "none";

  openModal("suspendModal");
}

function confirmSuspension() {
  const reason = $("suspendReason").value.trim();

  if (!reason) {
    $("suspendError").style.display = "block";
    return;
  }

  selectedUser.status = "Suspended";
  selectedUser.suspensionReason = reason;

  save(A + "users", users);
  log(`Suspended user account (${selectedUser.name}) — ${reason}`);

  selectedUser = null;
  closeModal("suspendModal");
  renderUsers();
}


/* ---------- LISTINGS ---------- */

function renderListings() {
  if (!$("listingRows")) return;

  const search = ($("listingSearch")?.value || "").toLowerCase();

  const shown = listings.filter(p =>
    `${p.name} ${p.farmer || ""}`.toLowerCase().includes(search)
  );

  $("listingRows").innerHTML = shown.length
    ? shown.map(p => `
        <tr>
          <td><b>${p.name}</b></td>
          <td>${p.farmer || "Farmer"}</td>
          <td>${p.category || "Produce"}</td>
          <td>${p.stock ?? 0} kg</td>
          <td>${tag(p.adminDisabled ? "Disabled" : "Active")}</td>
          <td>
            <button class="btn ${p.adminDisabled ? "light" : "red"}"
                    onclick="toggleListing('${p.id}')">
              ${p.adminDisabled ? "Restore" : "Disable"}
            </button>
          </td>
        </tr>
      `).join("")
    : empty(6, "No listings available.");
}

function toggleListing(id) {
  const listing = listings.find(p => String(p.id) === String(id));
  if (!listing) return;

  const action = listing.adminDisabled ? "Restore" : "Disable";
  if (!confirm(`${action} "${listing.name}"?`)) return;

  listing.adminDisabled = !listing.adminDisabled;
  saveFarmer("listings", listings);

  log(`${listing.adminDisabled ? "Disabled" : "Restored"} listing: ${listing.name}`);
  renderListings();
}


/* ---------- ORDERS ---------- */

function renderOrders() {
  if (!$("orderRows")) return;

  const search = ($("orderSearch")?.value || "").toLowerCase();
  const status = $("orderStatus")?.value || "All";

  const shown = orders.filter(o =>
    (status === "All" || o.status === status) &&
    `${o.id} ${o.product} ${o.buyer} ${o.farmer}`
      .toLowerCase().includes(search)
  );

  $("orderRows").innerHTML = shown.length
    ? shown.map(o => `
        <tr>
          <td>#${String(o.id).slice(-6)}</td>
          <td>${o.buyer || "Buyer"}</td>
          <td>${o.farmer || "Farmer"}</td>
          <td><b>${o.product}</b><br>${o.qty || 0} ${o.unit || "kg"}</td>
          <td>${tag(o.status)}</td>
          <td class="row-actions">
            <button class="btn light" onclick="holdOrder(${o.id})">
              ${o.status === "On Hold" ? "Resume" : "Hold"}
            </button>
            <button class="btn red" onclick="cancelOrder(${o.id})">Cancel</button>
          </td>
        </tr>
      `).join("")
    : empty(6, "No orders found.");
}

function holdOrder(id) {
  const order = orders.find(o => o.id === id);
  if (!order || ["Completed", "Cancelled"].includes(order.status)) return;

  if (order.status === "On Hold") {
    order.status = order.previousStatus || "Confirmed";
    delete order.previousStatus;
    log(`Resumed order #${id}`);
  } else {
    order.previousStatus = order.status;
    order.status = "On Hold";
    log(`Held order #${id}`);
  }

  saveFarmer("requests", orders);
  renderOrders();
}

function cancelOrder(id) {
  const order = orders.find(o => o.id === id);

  if (!order || !confirm(`Cancel order #${String(id).slice(-6)}?`))
    return;

  order.status = "Cancelled";

  saveFarmer("requests", orders);
  log(`Cancelled order #${id}`);
  renderOrders();
}


/* ---------- REPORTS ---------- */

function reports() {
  if (!$("activityRows")) return;

  $("userCount").textContent = users.length;
  $("listingCount").textContent = listings.length;
  $("orderCount").textContent = orders.length;

  $("activityRows").innerHTML = logs().map(item => `
    <tr><td>${item.date}</td><td>${item.user}</td><td>${item.activity}</td></tr>
  `).join("");

  $("export").onclick = () => {
    const rows = [
      ["Date", "User", "Activity"],
      ...logs().map(x => [x.date, x.user, x.activity])
    ];

    const csv = rows.map(row =>
      row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(",")
    ).join("\n");

    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([csv], { type:"text/csv" }));
    link.download = "animal-admin-report.csv";
    link.click();
  };
}


/* ---------- MODALS ---------- */

function openModal(id) {
  $(id)?.classList.add("show");
}

function closeModal(id) {
  $(id)?.classList.remove("show");
}


/* ---------- START ---------- */

document.addEventListener("DOMContentLoaded", () => {
  nav();
  dashboard();
  verification();
  application();
  renderUsers();
  renderListings();
  renderOrders();
  reports();

  listen("userSearch", "input", renderUsers);
  listen("userRole", "change", renderUsers);
  listen("userStatus", "change", renderUsers);
  listen("listingSearch", "input", renderListings);
  listen("orderSearch", "input", renderOrders);
  listen("orderStatus", "change", renderOrders);

  listen("cancelSuspend", "click", () => closeModal("suspendModal"));
  listen("confirmSuspend", "click", confirmSuspension);

  $("suspendModal")?.addEventListener("click", e => {
    if (e.target.id === "suspendModal") closeModal("suspendModal");
  });

  $("rejectModal")?.addEventListener("click", e => {
    if (e.target.id === "rejectModal") closeModal("rejectModal");
  });
});