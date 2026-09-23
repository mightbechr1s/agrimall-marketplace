const $=id=>document.getElementById(id);
const key="animal_farmer_demo_";

const load=n=>{
  try{return JSON.parse(localStorage.getItem(key+n))||[]}
  catch{return[]}
};

const save=(n,v)=>localStorage.setItem(key+n,JSON.stringify(v));
const peso=n=>"₱"+Number(n).toLocaleString("en-PH");

let orders=load("requests");

function renderOrders(){
  const list=$("orderList");
  if(!list)return;

  const filter=$("statusFilter")?.value||"All";
  const shown=orders.filter(o=>filter==="All"||o.status===filter);

  list.innerHTML=shown.length
    ?shown.map(o=>`
      <article class="order">

        <div class="order-info">
          <span class="tag">${o.status}</span>

          <h2>${o.product}</h2>

          <p class="muted">
            Request #${o.id} · ${o.farmer||"Farmer"}
          </p>

          <div class="details">
            <span>${o.qty} ${o.unit||"kg"}</span>
            <span>${o.method}</span>
            <span>${peso(o.price)} / ${o.unit||"kg"}</span>
          </div>

          ${
            o.status==="Awaiting Buyer"
              ?`<div class="fee">
                  Delivery fee: <strong>${peso(o.fee||0)}</strong>
                </div>`
              :""
          }

          <div class="total">
            ${
              o.fee!=null && o.status!=="Pending"
                ?`Total: ${peso(o.qty*o.price+o.fee)}`
                :`Produce subtotal: ${peso(o.qty*o.price)}`
            }
          </div>

          <p class="muted">${message(o)}</p>
        </div>

        <div class="actions">
          ${buttons(o)}
        </div>

      </article>
    `).join("")
    :`
      <div class="empty">
        <h2>${orders.length?"No orders in this category.":"No orders yet"}</h2>
        <p>${orders.length?"Choose another status filter.":"Browse produce and submit your first order request."}</p>
        ${orders.length?"":'<a class="btn" href="../catalog.html">Browse Produce</a>'}
      </div>
    `;
}

function message(o){
  const messages={
    "Pending":"Waiting for the farmer to review your request.",
    "Awaiting Buyer":"The farmer sent a delivery fee for your approval.",
    "Confirmed":"Your order has been confirmed.",
    "Preparing":"The farmer is preparing your order.",
    "Ready for Pickup":"Your order is ready for pickup.",
    "Out for Delivery":"Your order is on the way.",
    "Completed":"This order has been completed.",
    "Rejected":"The request was rejected.",
    "Cancelled":"You cancelled this request."
  };

  return messages[o.status]||"Order status updated.";
}

function buttons(o){
  if(o.status==="Pending")
    return `<button class="btn danger small"
            onclick="changeStatus(${o.id},'Cancelled')">
            Cancel Request
            </button>`;

  if(o.status==="Awaiting Buyer")
    return `
      <button class="btn danger small"
              onclick="changeStatus(${o.id},'Rejected')">
        Decline Fee
      </button>

      <button class="btn small"
              onclick="acceptFee(${o.id})">
        Accept Fee
      </button>
    `;

  return "";
}

function changeStatus(id,status){
  const order=orders.find(o=>o.id===id);
  if(!order)return;

  if(!confirm(
    status==="Cancelled"
      ?"Cancel this order request?"
      :"Decline this delivery fee?"
  ))return;

  order.status=status;
  save("requests",orders);
  renderOrders();
}

function acceptFee(id){
  const order=orders.find(o=>o.id===id);
  if(!order)return;

  const listings=load("listings");
  const product=listings.find(p=>
    p.id===order.productId || p.name===order.product
  );

  if(product){
    if(product.stock<order.qty)
      return alert("The farmer no longer has enough stock.");

    product.stock-=order.qty;
    save("listings",listings);
  }

  order.status="Confirmed";
  save("requests",orders);

  renderOrders();

  alert("Delivery fee accepted. Your order is now confirmed.");
}

$("statusFilter")?.addEventListener("change",renderOrders);

renderOrders();