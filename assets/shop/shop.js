(function () {
    const header = document.getElementById('shop-header');
    const shell = header?.querySelector('.shop-header-shell');
    const toggle = document.getElementById('shop-nav-toggle');
    const nav = document.getElementById('shop-nav');

    function syncHeaderOffset() {
        if (!header) return;
        document.documentElement.style.setProperty('--shop-header-offset', header.offsetHeight + 'px');
    }

    if (header) {
        syncHeaderOffset();
        window.addEventListener('resize', syncHeaderOffset, { passive: true });
        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(syncHeaderOffset);
            ro.observe(header);
        }
        window.addEventListener('scroll', function () {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
        }, { passive: true });
    }

    if (toggle && shell && nav) {
        toggle.addEventListener('click', function () {
            const open = shell.classList.toggle('is-nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    const reveals = document.querySelectorAll('.reveal');
    const isHomePage = document.body.classList.contains('shop-page-home');

    function escHtml(text) {
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    function productIdFromItem(p) {
        if (!p) return '';
        const raw = p.id ?? p.product_id ?? p.randomn_id ?? '';
        if (raw !== '' && raw !== null && raw !== undefined) {
            return String(raw);
        }
        const m = String(p.url || '').match(/[?&]id=(\d+)/);
        return m ? m[1] : '';
    }

    function buildProductActionsCell(p) {
        const pid = productIdFromItem(p);
        const cartBtn = pid
            ? '<button type="button" class="shop-btn shop-btn-accent shop-product-cart-btn" data-add-cart="' + escHtml(pid) + '" aria-label="Adaugă în coș" title="Adaugă în coș"><i class="fa-solid fa-cart-plus" aria-hidden="true"></i><span>Coș</span></button>'
            : '<span class="shop-product-cart-missing" title="Produs fără ID în catalog">—</span>';
        return '<td class="shop-product-actions">' + cartBtn +
            '<a class="shop-product-link" href="' + escHtml(p.url) + '" aria-label="Detalii produs" title="Detalii"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></td>';
    }

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-add-cart]');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        const id = btn.getAttribute('data-add-cart');
        const qty = btn.getAttribute('data-qty') || '1';
        const labelEl = btn.querySelector('span');
        const label = labelEl ? labelEl.textContent : btn.textContent;
        btn.disabled = true;
        try {
            const res = await fetch('api/shop-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', id: id, qty: qty }),
            });
            if (res.status === 401) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return;
            }
            const data = await res.json();
            if (data.ok) {
                let badge = document.querySelector('.shop-cart-badge');
                if (badge) {
                    badge.textContent = String(data.count);
                } else {
                    const link = document.querySelector('.shop-action-cart');
                    if (link) {
                        badge = document.createElement('em');
                        badge.className = 'shop-cart-badge';
                        badge.textContent = String(data.count);
                        link.appendChild(badge);
                    }
                }
                if (labelEl) {
                    labelEl.textContent = 'Adăugat';
                } else {
                    btn.textContent = '✓';
                }
                btn.classList.add('is-added');
                setTimeout(function () {
                    if (labelEl) {
                        labelEl.textContent = label;
                    } else {
                        btn.textContent = label;
                    }
                    btn.classList.remove('is-added');
                    btn.disabled = false;
                }, 1400);
            } else {
                alert(data.error || 'Nu s-a putut adăuga în coș.');
                btn.disabled = false;
            }
        } catch (err) {
            alert('Eroare de rețea.');
            btn.disabled = false;
        }
    });
    if (reveals.length && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -40px 0px', threshold: 0.08 });
        reveals.forEach(function (el) {
            if (isHomePage && el.className.indexOf('shop-home-') !== -1) {
                el.classList.add('is-visible');
                return;
            }
            if (el.classList.contains('shop-woo-section')) {
                el.classList.add('is-visible');
                return;
            }
            io.observe(el);
        });
    } else {
        reveals.forEach(function (el) { el.classList.add('is-visible'); });
    }

    const middleFrame = document.getElementById('middleFrame');
    if (middleFrame) {
        const dataEl = document.getElementById('shop-vehicle-data');
        let vehicleData = { catalog: {}, modelGroups: {}, mainCategoriesByModel: {}, brandCategories: {}, products: [], mainCategories: [], specialCategories: [] };
        if (dataEl) {
            try {
                vehicleData = JSON.parse(dataEl.textContent || '{}');
            } catch (e) {
                vehicleData = { catalog: {}, brandCategories: {}, products: [] };
            }
        }

        const panels = middleFrame.querySelectorAll('[data-gbg-panel]');
        const searchCells = middleFrame.querySelectorAll('.blu-gbg-search-cell[data-gbg-panel]');
        const brandLinks = middleFrame.querySelectorAll('.blu-gbg-brand-link');
        const specialTiles = middleFrame.querySelectorAll('[data-special-cat]');
        const mainCatTiles = middleFrame.querySelectorAll('.blu-gbg-cat-grid--fallback .blu-gbg-cat-tile');
        const modelList = document.getElementById('gbgModelList');
        const modelHint = document.getElementById('gbgModelHint');
        const productsBody = document.getElementById('gbg-products-body');
        const resultCount = document.getElementById('gbgResultCount');
        const paginationEl = document.getElementById('gbg-pagination');
        const emptyMsg = document.getElementById('gbg-empty');
        const gridWrap = document.getElementById('divGrid');
        const searchInfo = document.getElementById('divSearchInfo');
        const labelBrand = document.getElementById('gbgLabelBrand');
        const labelModel = document.getElementById('gbgLabelModel');
        const labelMainCat = document.getElementById('gbgLabelMainCat');
        const labelSpecialCat = document.getElementById('gbgLabelSpecialCat');
        const PAGE_SIZE = 12;

        const mainCatList = document.getElementById('gbgMainCatList');
        const mainCatFallback = document.getElementById('gbgMainCatFallback');
        const mainCatHint = document.getElementById('gbgMainCatHint');
        const divModelPanel = document.getElementById('divModel');
        const productsBackBtn = document.getElementById('gbgProductsBack');

        let state = { brand: '', brandId: '', model: '', modelLabel: '', mainCat: '', mainCatLabel: '', specialCat: '', specialQuery: '', page: 1 };
        let activePanel = 'allform';
        let modelsLoading = false;
        let modelFlow = { openGroupIndex: -1, groups: [] };

        function esc(text) {
            const d = document.createElement('div');
            d.textContent = String(text);
            return d.innerHTML;
        }

        function showEl(el) {
            if (!el) return;
            el.classList.remove('is-hidden');
            el.hidden = false;
        }

        function hideEl(el) {
            if (!el) return;
            el.classList.add('is-hidden');
            el.hidden = true;
        }

        function setPanel(name) {
            activePanel = name;
            panels.forEach(function (panel) {
                const show = panel.getAttribute('data-gbg-panel') === name;
                panel.classList.toggle('is-hidden', !show);
                panel.hidden = !show;
            });
            searchCells.forEach(function (cell) {
                cell.classList.toggle('is-active', cell.getAttribute('data-gbg-panel') === name);
            });
            if (name === 'allform') {
                hideEl(gridWrap);
                hideEl(searchInfo);
            }
        }

        function updateLabels() {
            if (labelBrand) labelBrand.textContent = state.brand || 'Selectează marca';
            if (labelModel) {
                labelModel.textContent = state.modelLabel || (state.model ? state.model.replace(/^[^—]+—\s*/, '') : 'Selectează modelul');
            }
            if (labelMainCat) {
                if (state.mainCatLabel) {
                    labelMainCat.textContent = state.mainCatLabel;
                } else {
                    const main = (vehicleData.mainCategories || []).find(function (c) { return c.id === state.mainCat; });
                    labelMainCat.textContent = main ? main.label : 'Selectează categoria';
                }
            }
            if (labelSpecialCat) {
                const spec = (vehicleData.specialCategories || []).find(function (c) { return c.id === state.specialCat; });
                labelSpecialCat.textContent = spec ? spec.label : 'Selectează categoria';
            }
        }

        function syncModelFlowVisibility() {
            const productsVisible = gridWrap && !gridWrap.hidden;
            if (productsBackBtn) {
                productsBackBtn.hidden = !productsVisible;
            }
            if (!divModelPanel) return;
            if (productsVisible && state.model) {
                hideEl(divModelPanel);
            } else if (!productsVisible && activePanel === 'model' && state.brand) {
                showEl(divModelPanel);
            }
        }

        function closeAllModelDrawers(exceptDetails) {
            if (!modelList) return;
            modelList.querySelectorAll('details.blu-model-acc').forEach(function (details) {
                if (exceptDetails && details === exceptDetails) return;
                details.open = false;
            });
        }

        function goBackFromProducts() {
            hideEl(gridWrap);
            hideEl(searchInfo);
            setPanel('model');
            showEl(divModelPanel);
            if (productsBackBtn) {
                productsBackBtn.hidden = true;
            }
            renderModelFlow(state.brand);
        }

        function selectModelVariant(model, groupIndex) {
            state.model = model.id;
            state.modelLabel = model.label || model.id;
            state.mainCat = '';
            state.mainCatLabel = '';
            state.specialCat = '';
            state.specialQuery = '';
            if (typeof groupIndex === 'number') {
                modelFlow.openGroupIndex = groupIndex;
            }
            updateLabels();
            renderMainCategories();
            showProducts();
            hideEl(divModelPanel);
            if (productsBackBtn) {
                productsBackBtn.hidden = false;
            }
        }

        function normalizeModelGroups(raw) {
            if (!Array.isArray(raw)) {
                return [];
            }
            return raw.map(function (row, idx) {
                if (!row || typeof row !== 'object') {
                    return null;
                }
                if (Array.isArray(row.models) && row.models.length) {
                    return {
                        group_id: row.group_id || ('grp-' + idx),
                        group: row.group || row.label || ('Model ' + (idx + 1)),
                        models: row.models,
                    };
                }
                if (row.id && (row.label || row.years)) {
                    return {
                        group_id: row.id,
                        group: row.label || row.id,
                        models: [row],
                    };
                }
                return null;
            }).filter(Boolean);
        }

        function renderModelFlow(brand) {
            if (!modelList) return;
            modelList.innerHTML = '';
            if (modelHint) hideEl(modelHint);

            const normalized = modelFlow.groups;
            if (!normalized.length) {
                if (modelHint) {
                    modelHint.textContent = brand
                        ? ('Nu avem modele pentru ' + brand + '. Poți căuta direct în catalog.')
                        : 'Selectează marca, apoi modelul vehiculului.';
                    showEl(modelHint);
                }
                return;
            }

            if (state.model) {
                normalized.forEach(function (groupRow, idx) {
                    if ((groupRow.models || []).some(function (m) { return m.id === state.model; })) {
                        modelFlow.openGroupIndex = idx;
                    }
                });
            }

            const shell = document.createElement('div');
            shell.className = 'blu-model-flow';

            const head = document.createElement('div');
            head.className = 'blu-model-flow__head';
            head.innerHTML = '<p class="blu-model-flow__eyebrow">' + esc(brand) + '</p>'
                + '<h2 class="blu-model-flow__title">Alege modelul</h2>';
            shell.appendChild(head);

            const list = document.createElement('div');
            list.className = 'blu-model-acc-list';

            normalized.forEach(function (groupRow, idx) {
                const models = groupRow.models || [];
                if (!models.length) return;

                const isOpen = modelFlow.openGroupIndex === idx;
                const details = document.createElement('details');
                details.className = 'blu-model-acc';
                details.open = isOpen;

                const summary = document.createElement('summary');
                summary.className = 'blu-model-acc__summary';
                summary.innerHTML = '<span class="blu-model-acc__label">' + esc(groupRow.group || ('Model ' + (idx + 1))) + '</span>'
                    + '<span class="blu-model-acc__meta">' + models.length + (models.length === 1 ? ' variantă' : ' variante') + '</span>'
                    + '<i class="fa-solid fa-chevron-down blu-model-acc__chevron" aria-hidden="true"></i>';

                const body = document.createElement('div');
                body.className = 'blu-model-acc__body';

                const grid = document.createElement('div');
                grid.className = 'blu-model-acc__variants';

                models.forEach(function (model) {
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'blu-model-acc__variant';
                    if (state.model === model.id) {
                        card.classList.add('is-selected');
                    }

                    const imgWrap = document.createElement('div');
                    imgWrap.className = 'blu-model-acc__variant-img';
                    if (model.img) {
                        const img = document.createElement('img');
                        img.src = model.img;
                        img.alt = model.label || '';
                        img.loading = 'lazy';
                        imgWrap.appendChild(img);
                    } else {
                        imgWrap.innerHTML = '<i class="fa-solid fa-car-side" aria-hidden="true"></i>';
                        imgWrap.classList.add('is-empty');
                    }

                    const label = document.createElement('span');
                    label.className = 'blu-model-acc__variant-label';
                    label.textContent = model.label || model.id;

                    card.appendChild(imgWrap);
                    card.appendChild(label);
                    card.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        selectModelVariant(model, idx);
                    });
                    grid.appendChild(card);
                });

                body.appendChild(grid);
                details.appendChild(summary);
                details.appendChild(body);

                details.addEventListener('toggle', function () {
                    if (details.open) {
                        closeAllModelDrawers(details);
                        modelFlow.openGroupIndex = idx;
                    } else if (modelFlow.openGroupIndex === idx) {
                        modelFlow.openGroupIndex = -1;
                    }
                });

                list.appendChild(details);
            });

            shell.appendChild(list);
            modelList.appendChild(shell);
        }

        function buildGbgModelPicker(brand, groups) {
            modelFlow.groups = normalizeModelGroups(groups);
            if (!state.model) {
                modelFlow.openGroupIndex = -1;
            }
            renderModelFlow(brand);
        }

        function renderModels(brand) {
            if (!modelList) return;
            const groups = normalizeModelGroups((vehicleData.modelGroups && vehicleData.modelGroups[brand]) || []);

            if (!brand) {
                modelList.innerHTML = '';
                if (modelHint) {
                    modelHint.textContent = 'Selectează marca, apoi modelul vehiculului.';
                    showEl(modelHint);
                }
                return;
            }

            if (groups.length) {
                buildGbgModelPicker(brand, groups);
                return;
            }

            if (modelsLoading) return;
            modelsLoading = true;
            modelList.innerHTML = '<p class="blu-gbg-panel-hint">Se încarcă modelele pentru ' + esc(brand) + '…</p>';

            fetch('api/gbg-models.php?brand=' + encodeURIComponent(brand))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    modelsLoading = false;
                    if (data.ok && Array.isArray(data.groups) && data.groups.length) {
                        if (!vehicleData.modelGroups) vehicleData.modelGroups = {};
                        vehicleData.modelGroups[brand] = data.groups;
                        buildGbgModelPicker(brand, data.groups);
                        return;
                    }
                    const models = (vehicleData.catalog && vehicleData.catalog[brand]) || [];
                    if (models.length) {
                        const byGroup = {};
                        models.forEach(function (m) {
                            const key = (m.group && String(m.group).trim()) || brand;
                            if (!byGroup[key]) byGroup[key] = [];
                            byGroup[key].push(m);
                        });
                        const catalogGroups = Object.keys(byGroup).map(function (key, idx) {
                            return { group_id: 'cat-' + idx, group: key, models: byGroup[key] };
                        });
                        buildGbgModelPicker(brand, catalogGroups);
                        return;
                    }
                    modelList.innerHTML = '';
                    if (modelHint) {
                        modelHint.textContent = 'Nu avem încă modele scanate pentru ' + brand + '. Poți căuta direct în catalog.';
                        showEl(modelHint);
                    }
                    const link = document.createElement('a');
                    link.className = 'blu-gbg-catalog-link';
                    link.href = 'catalog.php?q=' + encodeURIComponent(brand);
                    link.textContent = 'Deschide catalogul pentru ' + brand;
                    modelList.appendChild(link);
                })
                .catch(function () {
                    modelsLoading = false;
                    modelList.innerHTML = '';
                    if (modelHint) {
                        modelHint.textContent = 'Eroare la încărcarea modelelor. Încearcă din nou.';
                        showEl(modelHint);
                    }
                });
        }

        function renderMainCategories() {
            if (!mainCatList) return;
            mainCatList.innerHTML = '';
            const scraped = state.model ? ((vehicleData.mainCategoriesByModel || {})[state.model] || []) : [];
            const hasScraped = scraped.length > 0;
            if (mainCatFallback) {
                mainCatFallback.classList.toggle('is-hidden', hasScraped);
                mainCatFallback.hidden = hasScraped;
            }
            if (mainCatHint) {
                mainCatHint.textContent = hasScraped
                    ? 'Categorii GBG pentru modelul selectat'
                    : 'Selectează mai întâi modelul, apoi categoria principală.';
            }
            if (!hasScraped) {
                return;
            }
            scraped.forEach(function (groupRow) {
                const block = document.createElement('div');
                block.className = 'blu-gbg-form1-group';
                if (groupRow.group) {
                    const head = document.createElement('div');
                    head.className = 'blu-gbg-form1-group__title';
                    head.textContent = groupRow.group;
                    block.appendChild(head);
                }
                const grid = document.createElement('div');
                grid.className = 'blu-gbg-form1-items';
                (groupRow.items || []).forEach(function (item) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'blu-gbg-form1-item' + (state.mainCat === item.id ? ' is-active' : '');
                    btn.textContent = item.label;
                    btn.addEventListener('click', function () {
                        state.mainCat = item.id;
                        state.mainCatLabel = item.label;
                        state.specialCat = '';
                        state.specialQuery = '';
                        updateLabels();
                        renderMainCategories();
                        showProducts();
                        scrollToFinderTop();
                    });
                    grid.appendChild(btn);
                });
                block.appendChild(grid);
                mainCatList.appendChild(block);
            });
        }

        function getFilteredProducts() {
            let items = vehicleData.products || [];
            if (state.brand) {
                items = items.filter(function (p) { return p.brand === state.brand; });
            }
            if (state.mainCat || state.mainCatLabel) {
                const needle = (state.mainCatLabel || '').toLowerCase();
                const staticMain = (vehicleData.mainCategories || []).find(function (c) { return c.id === state.mainCat; });
                const searchNeedle = needle || (staticMain ? staticMain.label.toLowerCase() : state.mainCat.toLowerCase());
                if (searchNeedle) {
                    items = items.filter(function (p) {
                        return (p.cat || '').toLowerCase().indexOf(searchNeedle) !== -1 || (p.name || '').toLowerCase().indexOf(searchNeedle) !== -1;
                    });
                }
            }
            if (state.specialQuery) {
                const q = state.specialQuery.toLowerCase();
                items = items.filter(function (p) {
                    const hay = ((p.name || '') + ' ' + (p.cat || '')).toLowerCase();
                    return hay.indexOf(q) !== -1;
                });
            }
            if (state.model) {
                const labelNeedle = (state.modelLabel || state.model).toLowerCase();
                const tokens = labelNeedle.split(/[^a-z0-9]+/).filter(function (t) { return t.length >= 2; });
                if (tokens.length) {
                    items = items.filter(function (p) {
                        const hay = (p.name + ' ' + p.oem + ' ' + (p.cat || '')).toLowerCase();
                        return tokens.some(function (t) { return hay.indexOf(t) !== -1; });
                    });
                }
            }
            return items;
        }

        function buildPageList(current, total) {
            if (total <= 9) {
                const all = [];
                for (let i = 1; i <= total; i += 1) all.push(i);
                return all;
            }
            const pages = [1];
            const start = Math.max(2, current - 2);
            const end = Math.min(total - 1, current + 2);
            if (start > 2) pages.push('ellipsis');
            for (let i = start; i <= end; i += 1) pages.push(i);
            if (end < total - 1) pages.push('ellipsis');
            pages.push(total);
            return pages;
        }

        function renderPagination(totalItems, totalPages) {
            if (!paginationEl) return;
            if (totalItems <= PAGE_SIZE || totalPages <= 1) {
                paginationEl.innerHTML = '';
                hideEl(paginationEl);
                return;
            }
            showEl(paginationEl);
            paginationEl.innerHTML = '';
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'shop-vehicle-page-btn shop-vehicle-page-btn--nav';
            prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i>';
            prevBtn.disabled = state.page <= 1;
            prevBtn.addEventListener('click', function () {
                if (state.page > 1) { state.page -= 1; renderProducts(false); }
            });
            paginationEl.appendChild(prevBtn);
            buildPageList(state.page, totalPages).forEach(function (entry) {
                if (entry === 'ellipsis') {
                    const span = document.createElement('span');
                    span.className = 'shop-vehicle-page-ellipsis';
                    span.textContent = '…';
                    paginationEl.appendChild(span);
                    return;
                }
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shop-vehicle-page-btn' + (entry === state.page ? ' is-active' : '');
                btn.textContent = String(entry);
                btn.addEventListener('click', function () {
                    state.page = entry;
                    renderProducts(false);
                });
                paginationEl.appendChild(btn);
            });
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'shop-vehicle-page-btn shop-vehicle-page-btn--nav';
            nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>';
            nextBtn.disabled = state.page >= totalPages;
            nextBtn.addEventListener('click', function () {
                if (state.page < totalPages) { state.page += 1; renderProducts(false); }
            });
            paginationEl.appendChild(nextBtn);
        }

        function renderProducts(resetPage) {
            if (!productsBody) return;
            if (resetPage) state.page = 1;
            const items = getFilteredProducts();
            const totalPages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
            if (state.page > totalPages) state.page = totalPages;
            const start = (state.page - 1) * PAGE_SIZE;
            const shown = items.slice(start, start + PAGE_SIZE);
            productsBody.innerHTML = '';
            shown.forEach(function (p) {
                const tr = document.createElement('tr');
                const thumb = p.img
                    ? '<img class="shop-product-thumb" src="' + esc(p.img) + '" alt="" loading="lazy">'
                    : '<span class="shop-product-thumb shop-product-thumb--empty"><i class="fa-solid fa-image" aria-hidden="true"></i></span>';
                tr.innerHTML =
                    '<td class="shop-product-cell-name">' + thumb +
                    '<a href="' + esc(p.url) + '">' + esc(p.name) + '</a></td>' +
                    '<td>' + esc(p.cat || '—') + '</td>' +
                    '<td>' + esc(p.oem || '—') + '</td>' +
                    '<td class="shop-product-price">' + esc(p.price) + '</td>' +
                    buildProductActionsCell(p);
                productsBody.appendChild(tr);
            });
            if (resultCount) {
                resultCount.textContent = items.length + ' rezultate';
            }
            if (emptyMsg) {
                const empty = items.length === 0;
                emptyMsg.classList.toggle('is-hidden', !empty);
                emptyMsg.hidden = !empty;
            }
            renderPagination(items.length, totalPages);
        }

        function showProducts() {
            showEl(gridWrap);
            if (searchInfo) {
                const parts = [];
                if (state.brand) parts.push(state.brand);
                if (state.model) {
                parts.push(state.modelLabel || state.model);
            }
                if (state.mainCat) parts.push(labelMainCat ? labelMainCat.textContent : '');
                if (state.specialCat) parts.push(labelSpecialCat ? labelSpecialCat.textContent : '');
                searchInfo.textContent = parts.length ? 'Filtru activ: ' + parts.join(' · ') : '';
                searchInfo.classList.toggle('is-hidden', parts.length === 0);
                searchInfo.hidden = parts.length === 0;
            }
            renderProducts(true);
        }

        function scrollToFinderTop() {
            const menu = document.getElementById('searchMenu');
            if (!menu) return;
            const offsetVar = getComputedStyle(document.documentElement).getPropertyValue('--shop-header-offset').trim();
            const headerOffset = parseFloat(offsetVar) || 160;
            const top = menu.getBoundingClientRect().top + window.scrollY - headerOffset;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }

        function applyBrand(brand, brandId) {
            state.brand = brand;
            state.brandId = brandId || '';
            state.model = '';
            state.modelLabel = '';
            state.mainCat = '';
            state.mainCatLabel = '';
            state.specialCat = '';
            state.specialQuery = '';
            modelFlow.openGroupIndex = -1;
            modelFlow.groups = [];
            brandLinks.forEach(function (link) {
                link.classList.toggle('is-active', link.getAttribute('data-brand') === brand);
            });
            updateLabels();
            renderModels(brand);
            if (brand) {
                setPanel('model');
                hideEl(gridWrap);
                hideEl(searchInfo);
            } else {
                setPanel('allform');
                hideEl(gridWrap);
            }
        }

        searchCells.forEach(function (cell) {
            cell.addEventListener('click', function () {
                const panel = cell.getAttribute('data-gbg-panel');
                if (!panel) return;
                if (panel === activePanel && panel !== 'allform') {
                    setPanel('allform');
                    return;
                }
                setPanel(panel);
                if (panel === 'model' && state.brand) {
                    renderModels(state.brand);
                }
                if (panel === 'form1') {
                    renderMainCategories();
                }
            });
        });

        brandLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                applyBrand(link.getAttribute('data-brand') || '', link.getAttribute('data-brand-id') || '');
            });
        });

        specialTiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                state.specialCat = tile.getAttribute('data-special-cat') || '';
                state.specialQuery = tile.getAttribute('data-special-query') || '';
                state.mainCat = '';
                state.mainCatLabel = '';
                updateLabels();
                showProducts();
                setPanel('formint');
                scrollToFinderTop();
            });
        });

        mainCatTiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                state.mainCat = tile.getAttribute('data-main-cat') || '';
                state.mainCatLabel = tile.getAttribute('data-main-label') || '';
                state.specialCat = '';
                state.specialQuery = '';
                updateLabels();
                showProducts();
                scrollToFinderTop();
            });
        });

        setPanel('allform');
        updateLabels();
        renderMainCategories();

        if (productsBackBtn) {
            productsBackBtn.addEventListener('click', goBackFromProducts);
        }
    }

    const vehicleHub = document.getElementById('shop-vehicle-finder');
    if (vehicleHub) {
        const dataEl = document.getElementById('shop-vehicle-data');
        let vehicleData = { catalog: {}, brandCategories: {}, products: [] };
        if (dataEl) {
            try {
                vehicleData = JSON.parse(dataEl.textContent || '{}');
            } catch (e) {
                vehicleData = { catalog: {}, brandCategories: {}, products: [] };
            }
        }

        const brandSelect = document.getElementById('vf-brand');
        const modelSelect = document.getElementById('vf-model');
        const yearSelect = document.getElementById('vf-year');
        const brandTiles = vehicleHub.querySelectorAll('.shop-brand-tile');
        const categoriesPanel = document.getElementById('vf-categories-panel');
        const productsPanel = document.getElementById('vf-products-panel');
        const categoriesWrap = document.getElementById('vf-categories');
        const activeBrandLabel = document.getElementById('vf-active-brand');
        const productsBody = document.getElementById('vf-products-body');
        const resultCount = document.getElementById('vf-result-count');
        const paginationEl = document.getElementById('vf-pagination');
        const emptyMsg = document.getElementById('vf-empty');
        const resetCatBtn = document.getElementById('vf-reset-cat');
        const PAGE_SIZE = 8;

        let state = { brand: '', model: '', year: '', category: '', page: 1 };

        function esc(text) {
            const d = document.createElement('div');
            d.textContent = String(text);
            return d.innerHTML;
        }

        function showPanel(panel) {
            if (!panel) return;
            panel.classList.remove('is-hidden');
            panel.hidden = false;
        }

        function hidePanel(panel) {
            if (!panel) return;
            panel.classList.add('is-hidden');
            panel.hidden = true;
        }

        function setTilesActive(brand) {
            brandTiles.forEach(function (tile) {
                const active = tile.getAttribute('data-brand') === brand;
                tile.classList.toggle('is-active', active);
                tile.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function populateModels(brand) {
            if (!modelSelect) return;
            modelSelect.innerHTML = '<option value="">Alege modelul</option>';
            const models = (vehicleData.catalog && vehicleData.catalog[brand]) || [];
            models.forEach(function (model) {
                const opt = document.createElement('option');
                opt.value = model.id;
                opt.textContent = model.label + (model.years ? ' (' + model.years + ')' : '');
                opt.dataset.years = model.years || '';
                modelSelect.appendChild(opt);
            });
            modelSelect.disabled = models.length === 0;
        }

        function populateYears(yearsRange) {
            if (!yearSelect) return;
            yearSelect.innerHTML = '<option value="">Alege anul</option>';
            if (!yearsRange) {
                yearSelect.disabled = true;
                return;
            }
            const parts = String(yearsRange).split(/[–-]/);
            const start = parseInt(parts[0], 10);
            const end = parseInt(parts[1] || parts[0], 10);
            if (!isNaN(start) && !isNaN(end)) {
                for (let y = end; y >= start; y--) {
                    const opt = document.createElement('option');
                    opt.value = String(y);
                    opt.textContent = String(y);
                    yearSelect.appendChild(opt);
                }
            }
            yearSelect.disabled = yearSelect.options.length <= 1;
        }

        function renderCategories(brand) {
            if (!categoriesWrap) return;
            categoriesWrap.innerHTML = '';
            const cats = (vehicleData.brandCategories && vehicleData.brandCategories[brand]) || {};
            Object.keys(cats).sort().forEach(function (catName) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shop-vehicle-cat shop-pill-tab' + (state.category === catName ? ' is-active' : '');
                btn.setAttribute('role', 'tab');
                btn.setAttribute('aria-selected', state.category === catName ? 'true' : 'false');
                btn.innerHTML = esc(catName) + ' <em>(' + cats[catName] + ')</em>';
                btn.addEventListener('click', function () {
                    state.category = state.category === catName ? '' : catName;
                    renderCategories(brand);
                    renderProducts(true);
                });
                categoriesWrap.appendChild(btn);
            });
        }

        function modelTokens(modelId) {
            if (!modelId) return [];
            const found = (vehicleData.catalog[state.brand] || []).find(function (m) { return m.id === modelId; });
            const label = found ? found.label : modelId;
            return label.toLowerCase().split(/[^a-z0-9]+/).filter(function (t) { return t.length >= 2; });
        }

        function getFilteredProducts() {
            let items = (vehicleData.products || []).filter(function (p) {
                return !state.brand || p.brand === state.brand;
            });
            if (state.category) {
                items = items.filter(function (p) { return p.cat === state.category; });
            }
            if (state.model) {
                const tokens = modelTokens(state.model);
                if (tokens.length) {
                    items = items.filter(function (p) {
                        const hay = (p.name + ' ' + p.oem).toLowerCase();
                        return tokens.some(function (t) { return hay.indexOf(t) !== -1; });
                    });
                }
            }
            if (state.year) {
                items = items.filter(function (p) {
                    return String(p.name).indexOf(state.year) !== -1 || String(p.oem).indexOf(state.year) !== -1;
                });
            }
            return items;
        }

        function buildPageList(current, total) {
            const siblingCount = 2;
            if (total <= 9) {
                const all = [];
                for (let i = 1; i <= total; i += 1) {
                    all.push(i);
                }
                return all;
            }

            const pages = [1];
            const rangeStart = Math.max(2, current - siblingCount);
            const rangeEnd = Math.min(total - 1, current + siblingCount);

            if (rangeStart > 2) {
                pages.push('ellipsis');
            }
            for (let i = rangeStart; i <= rangeEnd; i += 1) {
                pages.push(i);
            }
            if (rangeEnd < total - 1) {
                pages.push('ellipsis');
            }
            pages.push(total);
            return pages;
        }

        function renderPagination(totalItems, totalPages) {
            if (!paginationEl) return;
            if (totalItems <= PAGE_SIZE || totalPages <= 1) {
                paginationEl.innerHTML = '';
                paginationEl.classList.add('is-hidden');
                paginationEl.hidden = true;
                return;
            }

            paginationEl.classList.remove('is-hidden');
            paginationEl.hidden = false;
            paginationEl.innerHTML = '';

            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'shop-vehicle-page-btn shop-vehicle-page-btn--nav';
            prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i>';
            prevBtn.setAttribute('aria-label', 'Pagina anterioară');
            prevBtn.disabled = state.page <= 1;
            prevBtn.addEventListener('click', function () {
                if (state.page > 1) {
                    state.page -= 1;
                    renderProducts(false);
                }
            });
            paginationEl.appendChild(prevBtn);

            buildPageList(state.page, totalPages).forEach(function (entry) {
                if (entry === 'ellipsis') {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'shop-vehicle-page-ellipsis';
                    ellipsis.textContent = '…';
                    ellipsis.setAttribute('aria-hidden', 'true');
                    paginationEl.appendChild(ellipsis);
                    return;
                }

                const pageNum = entry;
                const pageBtn = document.createElement('button');
                pageBtn.type = 'button';
                pageBtn.className = 'shop-vehicle-page-btn' + (pageNum === state.page ? ' is-active' : '');
                pageBtn.textContent = String(pageNum);
                pageBtn.setAttribute('aria-label', 'Pagina ' + pageNum);
                if (pageNum === state.page) {
                    pageBtn.setAttribute('aria-current', 'page');
                }
                pageBtn.addEventListener('click', function () {
                    state.page = pageNum;
                    renderProducts(false);
                });
                paginationEl.appendChild(pageBtn);
            });

            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'shop-vehicle-page-btn shop-vehicle-page-btn--nav';
            nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>';
            nextBtn.setAttribute('aria-label', 'Pagina următoare');
            nextBtn.disabled = state.page >= totalPages;
            nextBtn.addEventListener('click', function () {
                if (state.page < totalPages) {
                    state.page += 1;
                    renderProducts(false);
                }
            });
            paginationEl.appendChild(nextBtn);
        }

        function renderProducts(resetPage) {
            if (!productsBody) return;
            if (resetPage) {
                state.page = 1;
            }

            const items = getFilteredProducts();
            const totalPages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
            if (state.page > totalPages) {
                state.page = totalPages;
            }
            if (state.page < 1) {
                state.page = 1;
            }

            const start = (state.page - 1) * PAGE_SIZE;
            const shown = items.slice(start, start + PAGE_SIZE);

            productsBody.innerHTML = '';
            shown.forEach(function (p) {
                const tr = document.createElement('tr');
                const thumb = p.img
                    ? '<img class="shop-product-thumb" src="' + esc(p.img) + '" alt="" loading="lazy">'
                    : '<span class="shop-product-thumb shop-product-thumb--empty"><i class="fa-solid fa-image" aria-hidden="true"></i></span>';
                tr.innerHTML =
                    '<td class="shop-product-cell-name">' + thumb +
                    '<a href="' + esc(p.url) + '">' + esc(p.name) + '</a></td>' +
                    '<td>' + esc(p.cat || '—') + '</td>' +
                    '<td>' + esc(p.oem || '—') + '</td>' +
                    '<td class="shop-product-price">' + esc(p.price) + '</td>' +
                    buildProductActionsCell(p);
                productsBody.appendChild(tr);
            });

            if (resultCount) {
                const from = items.length ? start + 1 : 0;
                const to = Math.min(start + PAGE_SIZE, items.length);
                resultCount.textContent = items.length + ' rezultate' + (items.length ? ' · ' + from + '–' + to : '');
            }
            if (emptyMsg) {
                const isEmpty = items.length === 0;
                emptyMsg.classList.toggle('is-hidden', !isEmpty);
                emptyMsg.hidden = !isEmpty;
            }
            renderPagination(items.length, totalPages);
        }

        function applyBrand(brand) {
            state.brand = brand;
            state.model = '';
            state.year = '';
            state.category = '';
            if (brandSelect) brandSelect.value = brand;
            setTilesActive(brand);
            populateModels(brand);
            if (modelSelect) modelSelect.value = '';
            populateYears('');
            if (yearSelect) yearSelect.value = '';
            if (activeBrandLabel) activeBrandLabel.textContent = brand;
            if (brand) {
                showPanel(categoriesPanel);
                showPanel(productsPanel);
                renderCategories(brand);
                renderProducts(true);
            } else {
                hidePanel(categoriesPanel);
                hidePanel(productsPanel);
            }
        }

        brandTiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                applyBrand(tile.getAttribute('data-brand') || '');
            });
        });

        if (brandSelect) {
            brandSelect.addEventListener('change', function () {
                applyBrand(brandSelect.value);
            });
        }

        if (modelSelect) {
            modelSelect.addEventListener('change', function () {
                state.model = modelSelect.value;
                const opt = modelSelect.options[modelSelect.selectedIndex];
                populateYears(opt ? opt.dataset.years || '' : '');
                state.year = '';
                if (yearSelect) yearSelect.value = '';
                renderProducts(true);
            });
        }

        if (yearSelect) {
            yearSelect.addEventListener('change', function () {
                state.year = yearSelect.value;
                renderProducts(true);
            });
        }

        if (resetCatBtn) {
            resetCatBtn.addEventListener('click', function () {
                state.category = '';
                renderCategories(state.brand);
                renderProducts(true);
            });
        }
    }

    const homeTabs = document.querySelector('.shop-home-tabs');
    if (homeTabs) {
        const tabButtons = homeTabs.querySelectorAll('.shop-home-tab');
        const tabPanels = homeTabs.querySelectorAll('.shop-home-tabpanel');

        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.getAttribute('data-tab');
                if (!target) return;

                tabButtons.forEach(function (other) {
                    const active = other === btn;
                    other.classList.toggle('is-active', active);
                    other.setAttribute('aria-selected', active ? 'true' : 'false');
                    other.tabIndex = active ? 0 : -1;
                });

                tabPanels.forEach(function (panel) {
                    const active = panel.getAttribute('data-panel') === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });
            });
        });
    }

    document.querySelectorAll('[data-woo-tabs]').forEach(function (wrap) {
        const tabButtons = wrap.querySelectorAll('.shop-woo-tab');
        const tabPanels = wrap.querySelectorAll('.shop-woo-tabpanel');
        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.getAttribute('data-tab');
                if (!target) return;
                tabButtons.forEach(function (other) {
                    const active = other === btn;
                    other.classList.toggle('is-active', active);
                    other.setAttribute('aria-selected', active ? 'true' : 'false');
                    other.tabIndex = active ? 0 : -1;
                });
                tabPanels.forEach(function (panel) {
                    const active = panel.getAttribute('data-panel') === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });
            });
        });
    });
})();
