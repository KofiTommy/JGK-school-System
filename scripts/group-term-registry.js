document.addEventListener("DOMContentLoaded", function () {
    var list = document.getElementById("gtr-student-list");
    if (!list) {
        return;
    }

    var searchInput = document.getElementById("gtr-student-search");
    var selectVisibleButton = document.getElementById("gtr-select-visible");
    var clearVisibleButton = document.getElementById("gtr-clear-visible");
    var visibleCountNode = document.getElementById("gtr-visible-count");
    var selectedCountNode = document.getElementById("gtr-selected-count");
    var noResultsNode = document.getElementById("gtr-no-results");
    var cards = Array.prototype.slice.call(list.querySelectorAll("[data-student-card]"));

    function getCheckbox(card) {
        return card.querySelector('input[type="checkbox"]');
    }

    function syncSelectedState(card) {
        var checkbox = getCheckbox(card);
        if (!checkbox) {
            return;
        }
        card.classList.toggle("is-selected", checkbox.checked);
    }

    function isVisible(card) {
        return card.style.display !== "none";
    }

    function updateSelectedCount() {
        var selected = 0;
        cards.forEach(function (card) {
            var checkbox = getCheckbox(card);
            if (checkbox && checkbox.checked) {
                selected += 1;
            }
            syncSelectedState(card);
        });
        if (selectedCountNode) {
            selectedCountNode.textContent = String(selected);
        }
    }

    function updateVisibleCount() {
        var visible = 0;
        cards.forEach(function (card) {
            if (isVisible(card)) {
                visible += 1;
            }
        });
        if (visibleCountNode) {
            visibleCountNode.textContent = String(visible);
        }
        if (noResultsNode) {
            noResultsNode.hidden = visible !== 0;
        }
    }

    function applyFilter() {
        var term = searchInput ? searchInput.value.toLowerCase().trim() : "";
        cards.forEach(function (card) {
            var haystack = (card.getAttribute("data-search-text") || "").toLowerCase();
            card.style.display = !term || haystack.indexOf(term) !== -1 ? "" : "none";
        });
        updateVisibleCount();
    }

    cards.forEach(function (card) {
        var checkbox = getCheckbox(card);
        if (checkbox) {
            checkbox.addEventListener("change", updateSelectedCount);
        }
        syncSelectedState(card);
    });

    if (searchInput) {
        searchInput.addEventListener("input", applyFilter);
    }

    if (selectVisibleButton) {
        selectVisibleButton.addEventListener("click", function () {
            cards.forEach(function (card) {
                if (!isVisible(card)) {
                    return;
                }
                var checkbox = getCheckbox(card);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            updateSelectedCount();
        });
    }

    if (clearVisibleButton) {
        clearVisibleButton.addEventListener("click", function () {
            cards.forEach(function (card) {
                if (!isVisible(card)) {
                    return;
                }
                var checkbox = getCheckbox(card);
                if (checkbox) {
                    checkbox.checked = false;
                }
            });
            updateSelectedCount();
        });
    }

    applyFilter();
    updateSelectedCount();
});
