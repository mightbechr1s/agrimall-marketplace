const $ = id => document.getElementById(id);
const KEY = "animal_farmer_demo_";

const products = {
  tomatoes: {
    name: "Fresh Red Tomatoes",
    category: "Vegetables",
    farmer: "Mang Juan Farm",
    location: "La Union",
    price: 80,
    stock: 500,
    image: "https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=1000&q=85"
  },

  potatoes: {
    name: "Native Potatoes",
    category: "Root Crops",
    farmer: "Highland Co-op",
    location: "Benguet",
    price: 95,
    stock: 450,
    image: "https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=1000&q=85"
  },

  corn: {
    name: "Sweet Yellow Corn",
    category: "Grains",
    farmer: "Aling Rosa",
    location: "Pangasinan",
    price: 60,
    stock: 1200,
    image: "https://images.unsplash.com/photo-1551754655-cd27e38d2076?auto=format&fit=crop&w=1000&q=85"
  },

  onions: {
    name: "Fresh Red Onions",
    category: "Vegetables",
    farmer: "Ben's Farm",
    location: "Nueva Ecija",
    price: 120,
    stock: 800,
    image: "https://images.unsplash.com/photo-1508747703725-719777637510?auto=format&fit=crop&w=1000&q=85"
  },

  cabbage: {
    name: "Green Cabbage",
    category: "Vegetables",
    farmer: "Cruz Farm",
    location: "La Union",
    price: 55,
    stock: 350,
    image: "https://images.unsplash.com/photo-1594282486552-05b4d80fbb9f?auto=format&fit=crop&w=1000&q=85"
  }
};


const load = name => {
  try {
    return JSON.parse(localStorage.getItem(KEY + name)) || [];
  } catch {
    return [];
  }
};


const save = (name, value) => {
  localStorage.setItem(
    KEY + name,
    JSON.stringify(value)
  );
};


const peso = value =>
  "₱" +
  Number(value).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });


const key =
  new URLSearchParams(location.search).get("product") ||
  "tomatoes";


const base =
  products[key] ||
  products.tomatoes;


const listings =
  load("listings");


const saved = listings.find(p =>
  String(p.id) === key ||
  p.name?.toLowerCase() === base.name.toLowerCase()
);


/*
  Keep saved listing details such as stock,
  farmer, and price.

  The default product image is kept for the
  five built-in products so an old category
  image in localStorage cannot replace it.
*/
const product = {
  ...base,
  ...(saved || {}),

  image: base.image,

  price: Number(
    saved?.price ?? base.price
  ),

  stock: Number(
    saved?.stock ?? base.stock
  )
};


const productId =
  saved?.id || key;


function quantity() {
  return Math.max(
    1,
    Number($("quantity").value) || 1
  );
}


function method() {
  return (
    document.querySelector(
      '[name="method"]:checked'
    )?.value || "pickup"
  );
}


function renderProduct() {
  const image =
    $("productImage");

  image.src =
    product.image;

  image.alt =
    product.name;


  $("productCategory").textContent =
    product.category || "Produce";


  $("productName").textContent =
    product.name;


  $("productPrice").textContent =
    peso(product.price);


  $("farmerName").textContent =
    product.farmer || "Farmer";


  $("productLocation").textContent =
    product.location || "Philippines";


  $("availableStock").textContent =
    `${product.stock} kg`;


  $("quantity").max =
    product.stock;


  $("summaryProduct").textContent =
    product.name;


  $("backLink").href =
    `../produce/product-detail.html?product=${encodeURIComponent(key)}`;


  update();
}


function update() {
  let q =
    quantity();


  if (q > product.stock) {
    q = product.stock;
  }


  if (q < 1) {
    q = 1;
  }


  $("quantity").value =
    q;


  const delivery =
    method() === "delivery";


  const total =
    q * product.price;


  $("summaryQuantity").textContent =
    `${q} kg`;


  $("summaryMethod").textContent =
    delivery
      ? "Local Delivery"
      : "Pickup";


  $("priceCalculation").textContent =
    `${q} kg × ${peso(product.price)}`;


  $("summaryTotal").textContent =
    peso(total);


  $("deliveryNote").classList.toggle(
    "hidden",
    !delivery
  );


  $("deliverySummaryNote").classList.toggle(
    "hidden",
    !delivery
  );


  $("quantityError").classList.add(
    "hidden"
  );


  $("summaryTotal").classList.remove(
    "total-updated"
  );


  void $("summaryTotal").offsetWidth;


  $("summaryTotal").classList.add(
    "total-updated"
  );
}


$("decreaseQuantity").onclick = () => {
  $("quantity").value =
    Math.max(
      1,
      quantity() - 1
    );

  update();
};


$("increaseQuantity").onclick = () => {
  $("quantity").value =
    Math.min(
      product.stock,
      quantity() + 1
    );

  update();
};


$("quantity").addEventListener(
  "input",
  update
);


document
  .querySelectorAll('[name="method"]')
  .forEach(radio => {

    radio.addEventListener(
      "change",
      update
    );

  });


$("orderForm").addEventListener(
  "submit",
  event => {

    event.preventDefault();

    const q =
      quantity();


    if (
      !product.stock ||
      q > product.stock
    ) {

      $("quantityError")
        .classList
        .remove("hidden");

      return;
    }


    const requests =
      load("requests");


    requests.unshift({
      id: Date.now(),

      productId,

      product:
        product.name,

      farmer:
        product.farmer || "Farmer",

      buyer:
        "Buyer",

      qty:
        q,

      unit:
        "kg",

      price:
        product.price,

      method:
        method() === "delivery"
          ? "Local Delivery"
          : "Pickup",

      status:
        "Pending",

      fee:
        null
    });


    save(
      "requests",
      requests
    );


    location.href =
      "../buyer/my-orders.html";
  }
);


renderProduct();