const $ = id => document.getElementById(id);
const KEY = "animal_farmer_demo_";

const load = name => {
  try{return JSON.parse(localStorage.getItem(KEY + name)) || []}
  catch{return []}
};

const save = (name,data) =>
  localStorage.setItem(KEY + name,JSON.stringify(data));

const peso = n =>
  "₱" + Number(n).toLocaleString("en-PH");

let listings = load("listings");
let requests = load("requests");
let editId, rejectId;


/* DASHBOARD */

function renderDashboard(){
  if(!$("listingCount")) return;

  const active = [
    "Confirmed",
    "Preparing",
    "Ready for Pickup",
    "Out for Delivery"
  ];

  $("listingCount").textContent = listings.length;

  $("pendingCount").textContent =
    requests.filter(r => r.status === "Pending").length;

  $("activeCount").textContent =
    requests.filter(r => active.includes(r.status)).length;

  const recent = requests.slice(0,3);

  $("recentRequests").innerHTML = recent.length
    ? recent.map(r => `
      <article class="recent-request">

        <div>
          <span class="tag">${r.status}</span>
          <h3>${r.product}</h3>

          <p class="muted">
            ${r.buyer || "Buyer"} ·
            ${r.qty} ${r.unit || "kg"} ·
            ${r.method}
          </p>
        </div>

        <a class="btn light small"
           href="order-requests.html">
          View →
        </a>

      </article>
    `).join("")
    : `<div class="card empty">
         No order requests yet.
       </div>`;
}


/* LISTINGS */

function renderListings(){
  if(!$("listingGrid")) return;

  $("listingGrid").innerHTML = listings.length
    ? listings.map(p => `
      <article class="card product">

        ${p.image
          ? `<img src="${p.image}" alt="${p.name}">`
          : ""}

        <div class="content">

          <span class="tag">${p.category}</span>

          <h2>${p.name}</h2>

          <p class="muted">
            ${p.stock} ${p.unit || "kg"} available
          </p>

          <div class="bottom">

            <span class="price">
              ${peso(p.price)} / ${p.unit || "kg"}
            </span>

            <div class="actions">

              <button class="btn light small"
                      onclick="editListing(${p.id})">
                Edit
              </button>

              <button class="btn danger small"
                      onclick="removeListing(${p.id})">
                Remove
              </button>

            </div>

          </div>
        </div>

      </article>
    `).join("")
    : `<div class="card empty">
         No listings yet. Add your first product!
       </div>`;

  if($("listingTotal"))
    $("listingTotal").textContent = listings.length;
}


function removeListing(id){
  if(!confirm("Remove this listing?")) return;

  listings = listings.filter(p => p.id !== id);

  save("listings",listings);
  renderListings();
  renderDashboard();
}


/* EDIT LISTING */

function editListing(id){
  const p = listings.find(p => p.id === id);
  const form = $("editForm");

  if(!p || !form) return;

  editId = id;

  [
    "name",
    "stock",
    "price",
    "category",
    "unit",
    "availabilityDate",
    "description"
  ].forEach(field => {
    form.elements[field].value = p[field] || "";
  });

  $("editDialog").showModal();
}


$("editForm")?.addEventListener("submit",e => {
  e.preventDefault();

  const data =
    Object.fromEntries(new FormData(e.target));

  const p =
    listings.find(p => p.id === editId);

  if(!p) return;

  Object.assign(p,data,{
    stock:Number(data.stock),
    price:Number(data.price)
  });

  save("listings",listings);

  $("editDialog").close();

  renderListings();
  renderDashboard();
});


/* ADD LISTING */

const addDialog = $("addDialog");
const listingForm = $("listingForm");
const imageInput = $("productImage");
const preview = $("imagePreview");
const uploadText = $("uploadText");


imageInput?.addEventListener("change",() => {
  const file = imageInput.files[0];

  if(!file) return;

  if(file.size > 500000){
    imageInput.value = "";
    return alert(
      "Choose an image smaller than 500 KB."
    );
  }

  const reader = new FileReader();

  reader.onload = () => {
    preview.src = reader.result;
    preview.hidden = false;
    uploadText.hidden = true;
  };

  reader.readAsDataURL(file);
});


listingForm?.addEventListener("submit",e => {
  e.preventDefault();

  const data =
    Object.fromEntries(new FormData(listingForm));

  listings.unshift({
    id:Date.now(),
    ...data,
    stock:Number(data.stock),
    price:Number(data.price),
    image:preview.hidden ? "" : preview.src
  });

  try{
    save("listings",listings);

    addDialog.close();
    listingForm.reset();

    preview.hidden = true;
    uploadText.hidden = false;

    renderListings();
    renderDashboard();
  }
  catch{
    listings.shift();

    alert(
      "Could not save listing. Try a smaller image."
    );
  }
});


/* ORDERS */

function renderRequests(){
  if(!$("requestList")) return;

  const filter =
    $("statusFilter")?.value || "All";

  const shown =
    requests.filter(r =>
      filter === "All" ||
      r.status === filter
    );

  $("requestList").innerHTML = shown.length
    ? shown.map(r => `
      <article class="card request">

        <div>

          <span class="tag">
            ${r.status}
          </span>

          <h2>${r.product}</h2>

          <p class="muted">
            Request #${r.id} ·
            ${r.buyer || "Buyer"}
          </p>

          <p>
            ${r.qty} ${r.unit || "kg"} ×
            ${peso(r.price)}
            =
            <strong>
              ${peso(r.qty * r.price)}
            </strong>
          </p>

          <p class="muted">
            ${r.method}
            ${r.fee != null
              ? ` · Delivery fee: ${peso(r.fee)}`
              : ""}
          </p>

          ${
            r.status === "Rejected" &&
            r.rejectionReason
            ? `
              <div class="reject-note">
                <strong>
                  ${r.rejectionReason}
                </strong>

                <br>

                ${r.rejectionNote || ""}
              </div>
            `
            : ""
          }

        </div>

        <div class="actions">
          ${orderButtons(r)}
        </div>

      </article>
    `).join("")
    : `
      <div class="card empty">
        ${
          requests.length
            ? "No requests in this category."
            : "No order requests yet."
        }
      </div>
    `;
}


function orderButtons(r){

  if(r.status === "Pending"){

    const accept =
      r.method === "Local Delivery"
        ? btn(
            `quoteDelivery(${r.id})`,
            "Set Delivery Fee"
          )
        : btn(
            `updateStatus(${r.id},'Confirmed',true)`,
            "Accept"
          );

    return accept +
      btn(
        `openReject(${r.id})`,
        "Reject",
        "danger"
      );
  }


  if(r.status === "Confirmed")
    return btn(
      `updateStatus(${r.id},'Preparing')`,
      "Start Preparing"
    );


  if(r.status === "Preparing"){

    const next =
      r.method === "Pickup"
        ? "Ready for Pickup"
        : "Out for Delivery";

    return btn(
      `updateStatus(${r.id},'${next}')`,
      next
    );
  }


  if([
    "Ready for Pickup",
    "Out for Delivery"
  ].includes(r.status))
    return btn(
      `updateStatus(${r.id},'Completed')`,
      "Complete Order"
    );


  return `<span class="tag">
            ${r.status}
          </span>`;
}


const btn = (action,text,type="") =>
  `<button class="btn ${type} small"
           onclick="${action}">
     ${text}
   </button>`;


/* FARMER REJECTION */

function openReject(id){
  rejectId = id;

  $("rejectForm")?.reset();

  if($("rejectError"))
    $("rejectError").hidden = true;

  $("rejectModal")?.classList.add("show");
}


function closeReject(){
  rejectId = null;

  $("rejectModal")?.classList.remove("show");
}


function rejectOrder(){

  const reason =
    document.querySelector(
      'input[name="rejectReason"]:checked'
    )?.value;

  const note =
    $("rejectNote")?.value.trim();

  if(!reason || !note){
    $("rejectError").hidden = false;
    return;
  }

  const order =
    requests.find(r => r.id === rejectId);

  if(!order) return;

  order.status = "Rejected";
  order.rejectionReason = reason;
  order.rejectionNote = note;

  save("requests",requests);

  closeReject();
  renderRequests();
  renderDashboard();
}


/* DELIVERY */

function quoteDelivery(id){
  const order =
    requests.find(r => r.id === id);

  if(!order) return;

  const input =
    prompt("Enter delivery fee in pesos:");

  if(input === null) return;

  const fee = Number(input);

  if(
    !input.trim() ||
    Number.isNaN(fee) ||
    fee < 0
  )
    return alert("Enter a valid fee.");

  order.fee = fee;
  order.status = "Awaiting Buyer";

  save("requests",requests);

  renderRequests();
  renderDashboard();
}


/* STATUS */

function updateStatus(id,newStatus,deduct=false){

  const order =
    requests.find(r => r.id === id);

  if(!order) return;


  if(deduct && !order.stockDeducted){

    const product =
      listings.find(p =>
        String(p.id) === String(order.productId) ||
        p.name === order.product
      );

    if(product){

      if(product.stock < order.qty)
        return alert("Not enough stock.");

      product.stock -= order.qty;
      order.stockDeducted = true;

      save("listings",listings);
    }
  }


  order.status = newStatus;

  save("requests",requests);

  renderRequests();
  renderDashboard();
  renderListings();
}


/* EVENTS */

$("statusFilter")
  ?.addEventListener(
    "change",
    renderRequests
  );


$("cancelReject")
  ?.addEventListener(
    "click",
    closeReject
  );


$("confirmReject")
  ?.addEventListener(
    "click",
    rejectOrder
  );


$("rejectModal")
  ?.addEventListener("click",e => {

    if(e.target === $("rejectModal"))
      closeReject();

  });


if(
  addDialog &&
  new URLSearchParams(location.search)
    .get("add") === "true"
){
  addDialog.showModal();

  history.replaceState(
    {},
    "",
    "listings.html"
  );
}


renderDashboard();
renderListings();
renderRequests();