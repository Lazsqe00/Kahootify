/**
 * Modern B2B SaaS Client-side Table Pagination Engine
 * Zero-dependency, lightweight, high performance DOM row toggling.
 */
class SaasTablePagination {
  constructor(options) {
    this.table = typeof options.table === 'string' ? document.querySelector(options.table) : options.table;
    this.container = typeof options.container === 'string' ? document.querySelector(options.container) : options.container;
    this.itemNoun = options.itemNoun || 'items';
    this.pageSize = parseInt(options.pageSize, 10) || 10;
    this.currentPage = 1;
    this.allowedPageSizes = options.allowedPageSizes || [10, 25, 50];

    if (!this.table || !this.container) return;

    this.tbody = this.table.querySelector('tbody');
    if (!this.tbody) return;

    this.init();
  }

  getRows() {
    return Array.from(this.tbody.querySelectorAll('tr:not(.no-pagination)'));
  }

  init() {
    this.render();
  }

  setPage(page) {
    const rows = this.getRows();
    const totalPages = Math.ceil(rows.length / this.pageSize) || 1;
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    this.currentPage = page;
    this.render();
  }

  setPageSize(size) {
    this.pageSize = parseInt(size, 10) || 10;
    this.currentPage = 1;
    this.render();
  }

  render() {
    const rows = this.getRows();
    const totalCount = rows.length;
    const totalPages = Math.ceil(totalCount / this.pageSize) || 1;

    if (this.currentPage > totalPages) {
      this.currentPage = totalPages;
    }

    // Toggle rows visibility
    const startIndex = (this.currentPage - 1) * this.pageSize;
    const endIndex = Math.min(startIndex + this.pageSize, totalCount);

    rows.forEach((row, idx) => {
      if (idx >= startIndex && idx < endIndex) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });

    // If no rows, hide or show 0
    const displayStart = totalCount === 0 ? 0 : startIndex + 1;
    const displayEnd = endIndex;

    // Render pagination footer UI in clean English
    this.container.innerHTML = `
      <div class="saas-pagination-left">
        <p class="saas-pagination-info">
          Showing <span class="highlight">${displayStart}</span> – <span class="highlight">${displayEnd}</span> of <span class="highlight">${totalCount}</span> ${this.itemNoun}
        </p>
        <div class="d-inline-flex align-items-center">
          <select class="saas-pagination-size-select" aria-label="Rows per page">
            ${this.allowedPageSizes.map(sz => `<option value="${sz}" ${sz === this.pageSize ? 'selected' : ''}>${sz} / page</option>`).join('')}
          </select>
        </div>
      </div>

      <div class="saas-pagination-right">
        <button type="button" class="saas-page-nav-btn saas-prev-btn ${this.currentPage === 1 ? 'disabled' : ''}" ${this.currentPage === 1 ? 'disabled' : ''}>
          <i class="fas fa-chevron-left" style="font-size: 11px;"></i>
          <span>Previous</span>
        </button>

        <div class="saas-page-numbers d-inline-flex align-items-center" style="gap: 4px;">
          ${this.generatePageButtons(totalPages)}
        </div>

        <button type="button" class="saas-page-nav-btn saas-next-btn ${this.currentPage === totalPages ? 'disabled' : ''}" ${this.currentPage === totalPages ? 'disabled' : ''}>
          <span>Next</span>
          <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
        </button>
      </div>
    `;

    // Attach event listeners
    const sizeSelect = this.container.querySelector('.saas-pagination-size-select');
    if (sizeSelect) {
      sizeSelect.addEventListener('change', (e) => {
        this.setPageSize(e.target.value);
      });
    }

    const prevBtn = this.container.querySelector('.saas-prev-btn');
    if (prevBtn && this.currentPage > 1) {
      prevBtn.addEventListener('click', () => {
        this.setPage(this.currentPage - 1);
      });
    }

    const nextBtn = this.container.querySelector('.saas-next-btn');
    if (nextBtn && this.currentPage < totalPages) {
      nextBtn.addEventListener('click', () => {
        this.setPage(this.currentPage + 1);
      });
    }

    const pageButtons = this.container.querySelectorAll('.saas-page-btn[data-page]');
    pageButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        const pageNum = parseInt(e.currentTarget.getAttribute('data-page'), 10);
        if (pageNum && pageNum !== this.currentPage) {
          this.setPage(pageNum);
        }
      });
    });
  }

  generatePageButtons(totalPages) {
    if (totalPages <= 1) {
      return `<button type="button" class="saas-page-btn active" data-page="1">1</button>`;
    }

    let pages = [];

    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      if (this.currentPage <= 4) {
        pages = [1, 2, 3, 4, 5, '...', totalPages];
      } else if (this.currentPage >= totalPages - 3) {
        pages = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
      } else {
        pages = [1, '...', this.currentPage - 1, this.currentPage, this.currentPage + 1, '...', totalPages];
      }
    }

    return pages.map(p => {
      if (p === '...') {
        return `<span class="saas-page-btn dots">…</span>`;
      }
      const isActive = p === this.currentPage;
      return `<button type="button" class="saas-page-btn ${isActive ? 'active' : ''}" data-page="${p}">${p}</button>`;
    }).join('');
  }
}

window.SaasTablePagination = SaasTablePagination;
