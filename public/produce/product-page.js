const products = {
  tomatoes: {
    name: "Fresh Red Tomatoes",
    category: "Vegetables",
    farmer: "Mang Juan Farm",
    location: "La Union",
    price: 80,
    stock: "500 kg",
    description:
      "Fresh red tomatoes, perfect for salads, sauces, and everyday cooking.",
    image:
      "https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=1000&q=85"
  },

  potatoes: {
    name: "Native Potatoes",
    category: "Root Crops",
    farmer: "Highland Co-op",
    location: "Benguet",
    price: 95,
    stock: "450 kg",
    description:
      "Fresh native potatoes from Benguet, perfect for everyday cooking.",
    image:
      "https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=1000&q=85"
  },

  corn: {
    name: "Sweet Yellow Corn",
    category: "Grains",
    farmer: "Aling Rosa",
    location: "Pangasinan",
    price: 60,
    stock: "1,200 kg",
    description:
      "Sweet and crunchy yellow corn, perfect for boiling or grilling.",
    image:
      "https://images.unsplash.com/photo-1551754655-cd27e38d2076?auto=format&fit=crop&w=1000&q=85"
  },

  onions: {
    name: "Fresh Red Onions",
    category: "Vegetables",
    farmer: "Ben's Farm",
    location: "Nueva Ecija",
    price: 120,
    stock: "800 kg",
    description:
      "Fresh red onions with a rich flavor, perfect for everyday cooking.",
    image:
      "https://images.unsplash.com/photo-1508747703725-719777637510?auto=format&fit=crop&w=1000&q=85"
  },

  cabbage: {
    name: "Green Cabbage",
    category: "Vegetables",
    farmer: "Cruz Farm",
    location: "La Union",
    price: 55,
    stock: "350 kg",
    description:
      "Fresh and crisp green cabbage, perfect for salads, stir-fries, and soups.",
    image:
      "https://images.unsplash.com/photo-1594282486552-05b4d80fbb9f?auto=format&fit=crop&w=1000&q=85"
  }
};


const productKey =
  new URLSearchParams(window.location.search).get("product") ||
  document.body.dataset.product;

const product = products[productKey];

const productPage =
  document.getElementById("product-page");


if (productPage) {

  if (!product) {

    document.title = "AniMAL - Product Not Found";

    productPage.innerHTML = `
      <main class="container">

        <a href="../catalog.html" class="back-link">
          ← Back to Catalog
        </a>

        <h1>Product not found</h1>

        <p>
          Please choose a product from the catalog.
        </p>

      </main>
    `;

  } else {

    document.title =
      `AniMAL - ${product.name}`;

    productPage.innerHTML = `

      <header class="site-header">

        <nav class="nav">

          <a
            href="../index.html"
            class="brand"
          >
            AniMAL
          </a>


          <form
            action="../catalog.html"
            method="GET"
            class="search"
          >

            <input
              type="search"
              name="search"
              placeholder="Search fresh produce..."
              aria-label="Search fresh produce"
            >

          </form>


          <a
            href="../buyer/my-orders.html"
            class="nav-link"
          >
            My Orders
          </a>


          <a
            href="../catalog.html"
            class="nav-link"
          >
            Browse Produce
          </a>


          <a
            href="../auth/login.html"
            class="nav-link login-link"
          >
            Login / Register
          </a>

        </nav>

      </header>


      <main class="container">

        <a
          href="../catalog.html"
          class="back-link fade-up"
        >
          <span aria-hidden="true">←</span>
          Back to Catalog
        </a>


        <section class="product-panel">


          <div class="fade-left">

            <img
              src="${product.image}"
              alt="${product.name}"
              class="product-photo"
            >

            <p class="photo-caption">
              Fresh produce from
              ${product.farmer},
              ${product.location}
            </p>

          </div>


          <div class="fade-right">

            <span class="category">
              ${product.category}
            </span>


            <h1>
              ${product.name}
            </h1>


            <p class="subtitle">
              Fresh produce sourced from local farmers.
            </p>


            <div class="price">

              ₱${product.price}

              <small>
                / kg
              </small>

            </div>


            <div class="details">


              <div class="detail-row">

                <span class="detail-label">
                  Farmer
                </span>

                <span class="detail-value">
                  ${product.farmer}
                </span>

              </div>


              <div class="detail-row">

                <span class="detail-label">
                  Location
                </span>

                <span class="detail-value">
                  ${product.location}
                </span>

              </div>


              <div class="detail-row">

                <span class="detail-label">
                  Available Quantity
                </span>

                <span class="detail-value stock">
                  ${product.stock}
                </span>

              </div>


            </div>


            <div class="description">

              <h2>
                Product Description
              </h2>

              <p>
                ${product.description}
              </p>

            </div>


            <a
              href="../orders/order-request.html?product=${encodeURIComponent(productKey)}"
              class="order-button"
            >

              Proceed to Order

              <span aria-hidden="true">
                →
              </span>

            </a>


          </div>

        </section>

      </main>


      <footer>
        © 2026 AniMAL Marketplace
      </footer>

    `;
  }
}