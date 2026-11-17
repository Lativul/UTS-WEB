const state = {
  books: [],
  members: [],
  loans: [],
  activeView: "home",
  category: "Semua",
  pendingBook: null,
};

const viewLabels = {
  home: "Beranda",
  catalog: "Katalog buku",
  loans: "Transaksi",
  members: "Anggota",
};

const paletteClasses = Array.from({ length: 12 }, (_, index) => `cover-color-${index + 1}`);
const memberPalette = ["#e5f3eb", "#f5e9dd", "#e9e9f2", "#e6f2f2"];

document.addEventListener("DOMContentLoaded", initialize);

async function initialize() {
  setDates();
  document.getElementById("footer-year").textContent = new Date().getFullYear();
  bindNavigation();
  bindCatalogControls();
  bindMemberForm();
  bindBorrowForm();

  try {
    await loadData();
  } catch (error) {
    showToast(error.message || "Tidak dapat memuat data perpustakaan.", true);
  }
}

async function api(action, options = {}) {
  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
    headers: { Accept: "application/json", ...(options.body ? { "Content-Type": "application/json" } : {}) },
    ...options,
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(result.error || "Terjadi kesalahan. Silakan coba lagi.");
  return result;
}

async function loadData() {
  const data = await api("bootstrap");
  state.books = data.books;
  state.members = data.members;
  state.loans = data.loans;
  populateCategoryFilter();
  renderAll();
}

function setDates() {
  const now = new Date();
  const longDate = new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(now);
  document.getElementById("welcome-date").textContent = longDate;
  document.getElementById("today-label").textContent = new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(now);
}

function bindNavigation() {
  document.querySelectorAll("[data-view]").forEach((button) => {
    button.addEventListener("click", () => setView(button.dataset.view));
  });
  document.querySelectorAll("[data-go-view]").forEach((button) => {
    button.addEventListener("click", () => setView(button.dataset.goView));
  });
}

function setView(view) {
  if (!viewLabels[view]) return;
  state.activeView = view;

  document.querySelectorAll(".view-panel").forEach((panel) => {
    const isSelected = panel.id === `view-${view}`;
    panel.hidden = !isSelected;
    panel.classList.toggle("is-visible", isSelected);
  });
  document.querySelectorAll(".nav-link[data-view]").forEach((button) => {
    const isSelected = button.dataset.view === view;
    button.classList.toggle("is-active", isSelected);
    if (isSelected) button.setAttribute("aria-current", "page");
    else button.removeAttribute("aria-current");
  });
  document.getElementById("breadcrumb-current").textContent = viewLabels[view];
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function bindCatalogControls() {
  const search = document.getElementById("search-input");
  const filter = document.getElementById("category-filter");
  search.addEventListener("input", renderCatalog);
  filter.addEventListener("change", () => {
    state.category = filter.value;
    renderCategoryChips();
    renderCatalog();
  });

  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      setView("catalog");
      search.focus();
    }
  });
}

function populateCategoryFilter() {
  const categories = [...new Set(state.books.map((book) => book.category))].sort((a, b) => a.localeCompare(b, "id"));
  const select = document.getElementById("category-filter");
  select.innerHTML = '<option value="Semua">Semua kategori</option>';
  categories.forEach((category) => {
    const option = document.createElement("option");
    option.value = category;
    option.textContent = category;
    select.append(option);
  });
  renderCategoryChips();
}

function renderCategoryChips() {
  const categories = ["Semua", ...new Set(state.books.map((book) => book.category))];
  const container = document.getElementById("category-chips");
  container.innerHTML = categories
    .map(
      (category) => `
        <button class="category-chip${state.category === category ? " is-selected" : ""}"
          type="button" data-category="${escapeHtml(category)}"
          aria-pressed="${state.category === category}">${escapeHtml(category)}</button>`,
    )
    .join("");
  container.querySelectorAll("[data-category]").forEach((button) => {
    button.addEventListener("click", () => {
      state.category = button.dataset.category;
      document.getElementById("category-filter").value = state.category;
      renderCategoryChips();
      renderCatalog();
    });
  });
}

function renderAll() {
  renderStats();
  renderFeatured();
  renderCatalog();
  renderLoans();
  renderMembers();
}

function renderStats() {
  document.getElementById("stat-books").textContent = state.books.length;
  document.getElementById("stat-members").textContent = state.members.length;
  document.getElementById("stat-loans").textContent = state.loans.filter((loan) => loan.status === "active").length;
  document.getElementById("stat-ebooks").textContent = state.books.filter((book) => book.format === "ebook").length;
  document.getElementById("nav-loan-count").textContent = state.loans.filter((loan) => loan.status === "active").length;
}

function renderFeatured() {
  const featured = [...state.books].sort((a, b) => Number(b.featured) - Number(a.featured)).slice(0, 4);
  document.getElementById("featured-books").innerHTML = featured.length
    ? featured.map((book, index) => bookCard(book, index)).join("")
    : '<p class="empty-grid">Koleksi belum tersedia.</p>';
  attachBorrowButtons(document.getElementById("featured-books"));
}

function renderCatalog() {
  const query = document.getElementById("search-input").value.trim().toLocaleLowerCase("id");
  const filtered = state.books.filter((book) => {
    const matchesCategory = state.category === "Semua" || book.category === state.category;
    const haystack = `${book.title} ${book.author} ${book.category}`.toLocaleLowerCase("id");
    return matchesCategory && haystack.includes(query);
  });

  document.getElementById("catalog-result-count").textContent = `${filtered.length} judul ditemukan`;
  document.getElementById("catalog-books").innerHTML = filtered.length
    ? filtered.map((book, index) => bookCard(book, index)).join("")
    : '<p class="empty-grid">Belum ada buku yang cocok. Coba kata kunci atau kategori lain.</p>';
  attachBorrowButtons(document.getElementById("catalog-books"));
}

function bookCard(book, index) {
  const stock = Number(book.stock);
  const coverClass = paletteClasses[(Number(book.id) - 1) % paletteClasses.length] || paletteClasses[index % paletteClasses.length];
  const icon = ["✳", "✦", "◯", "❋", "⌁", "◇", "☼", "✺"][(Number(book.id) - 1) % 8] || "✳";
  const disabled = stock <= 0;

  return `
    <article class="book-card">
      <div class="book-cover ${coverClass}">
        <span class="cover-ebook">${book.format === "ebook" ? "E-BOOK" : "CETAK"}</span>
        <span class="cover-symbol" aria-hidden="true">${icon}</span>
        <span class="cover-copy">${escapeHtml(book.title)}</span>
      </div>
      <div class="book-info">
        <span class="book-category">${escapeHtml(book.category)}</span>
        <h3 title="${escapeHtml(book.title)}">${escapeHtml(book.title)}</h3>
        <p class="book-author">${escapeHtml(book.author)}</p>
        <div class="book-card-bottom">
          <span class="book-stock${stock > 0 && stock < 3 ? " is-low" : ""}">${stock > 0 ? `${stock} tersedia` : "Habis"}</span>
          <button class="borrow-button" type="button" data-borrow="${Number(book.id)}" ${disabled ? "disabled" : ""}>
            ${disabled ? "Kosong" : "Pinjam"} ${disabled ? "" : '<span aria-hidden="true">↗</span>'}
          </button>
        </div>
      </div>
    </article>`;
}

function attachBorrowButtons(container) {
  container.querySelectorAll("[data-borrow]:not(:disabled)").forEach((button) => {
    button.addEventListener("click", () => openBorrowDialog(Number(button.dataset.borrow)));
  });
}

function openBorrowDialog(bookId) {
  const book = state.books.find((item) => Number(item.id) === bookId);
  if (!book) return;
  if (!state.members.length) {
    setView("members");
    showToast("Daftarkan anggota terlebih dahulu untuk meminjam.");
    document.getElementById("member-name").focus();
    return;
  }

  state.pendingBook = book;
  document.getElementById("dialog-book-title").textContent = book.title;
  document.getElementById("borrow-error").textContent = "";
  const select = document.getElementById("borrower-select");
  select.innerHTML = state.members
    .map((member) => `<option value="${Number(member.id)}">${escapeHtml(member.name)}</option>`)
    .join("");

  const dialog = document.getElementById("borrow-dialog");
  dialog.showModal();
}

function bindBorrowForm() {
  const dialog = document.getElementById("borrow-dialog");
  const form = document.getElementById("borrow-form");
  document.querySelectorAll("[data-close-dialog]").forEach((button) => {
    button.addEventListener("click", () => dialog.close());
  });
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener("close", () => {
    form.reset();
    state.pendingBook = null;
  });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!state.pendingBook || !form.reportValidity()) return;

    const submit = form.querySelector('[type="submit"]');
    const error = document.getElementById("borrow-error");
    submit.disabled = true;
    error.textContent = "";
    try {
      await api("borrow", {
        method: "POST",
        body: JSON.stringify({
          media_id: state.pendingBook.id,
          member_id: document.getElementById("borrower-select").value,
        }),
      });
      dialog.close();
      await loadData();
      setView("loans");
      showToast("Peminjaman berhasil dicatat.");
    } catch (requestError) {
      error.textContent = requestError.message;
    } finally {
      submit.disabled = false;
    }
  });
}

function renderLoans() {
  const body = document.getElementById("loans-table-body");
  const empty = state.loans.length === 0;
  document.getElementById("loans-empty").hidden = !empty;
  body.closest("table").hidden = empty;
  document.getElementById("loan-total-badge").textContent = `${state.loans.length} transaksi`;

  body.innerHTML = [...state.loans]
    .sort((a, b) => b.id - a.id)
    .map(
      (loan) => `
        <tr>
          <td><strong>${escapeHtml(loan.media_title)}</strong><small>${escapeHtml(loan.author)}</small></td>
          <td>${escapeHtml(loan.member_name)}</td>
          <td>${formatDate(loan.loan_date)}</td>
          <td>${formatDate(loan.due_date)}</td>
          <td><span class="status-pill">${loan.status === "active" ? "Dipinjam" : "Dikembalikan"}</span></td>
        </tr>`,
    )
    .join("");
}

function bindMemberForm() {
  const form = document.getElementById("member-form");
  const nameInput = document.getElementById("member-name");
  const emailInput = document.getElementById("member-email");

  nameInput.addEventListener("input", () => {
    document.getElementById("member-name-error").textContent = "";
  });
  emailInput.addEventListener("input", () => {
    document.getElementById("member-email-error").textContent = "";
  });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const nameError = document.getElementById("member-name-error");
    const emailError = document.getElementById("member-email-error");
    nameError.textContent = name.length < 2 ? "Nama harus terdiri dari minimal 2 karakter." : "";
    emailError.textContent = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "Masukkan alamat email yang valid." : "";
    if (nameError.textContent || emailError.textContent) return;

    const submit = form.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      await api("add_member", {
        method: "POST",
        body: JSON.stringify({ name, email }),
      });
      form.reset();
      await loadData();
      showToast("Anggota baru berhasil didaftarkan.");
    } catch (error) {
      emailError.textContent = error.message;
    } finally {
      submit.disabled = false;
    }
  });
}

function renderMembers() {
  document.getElementById("member-total-badge").textContent = `${state.members.length} anggota`;
  document.getElementById("members-description").textContent =
    `${state.members.length} pembaca terdaftar di Ruang Baca.`;
  document.getElementById("members-empty").hidden = state.members.length > 0;
  document.getElementById("member-list").innerHTML = [...state.members]
    .sort((a, b) => a.name.localeCompare(b.name, "id"))
    .map((member, index) => {
      const activeLoans = state.loans.filter(
        (loan) => Number(loan.member_id) === Number(member.id) && loan.status === "active",
      ).length;
      const initials = member.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase("id");
      return `
        <article class="member-row" style="--member-color:${memberPalette[index % memberPalette.length]}">
          <span class="member-initials" aria-hidden="true">${escapeHtml(initials)}</span>
          <span class="member-details"><strong>${escapeHtml(member.name)}</strong><span>${escapeHtml(member.email)}</span></span>
          <span class="member-loan-count">${activeLoans} pinjaman</span>
        </article>`;
    })
    .join("");
}

function formatDate(value) {
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return escapeHtml(value);
  return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" }).format(date);
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => {
    const entities = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return entities[character];
  });
}

function showToast(message, isError = false) {
  const region = document.getElementById("toast-region");
  const toast = document.createElement("div");
  toast.className = `toast${isError ? " is-error" : ""}`;
  toast.setAttribute("role", isError ? "alert" : "status");
  toast.textContent = message;
  region.append(toast);
  window.setTimeout(() => toast.remove(), 3800);
}
