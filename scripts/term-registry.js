document.addEventListener("DOMContentLoaded", function () {
    var list = document.getElementById("trr-student-list");
    if (!list) {
        return;
    }

    var searchInput = document.getElementById("trr-student-search");
    var visibleCountNode = document.getElementById("trr-visible-count");
    var noResultsNode = document.getElementById("trr-no-results");
    var cards = Array.prototype.slice.call(list.querySelectorAll("[data-student-card]"));

    function isVisible(card) {
        return card.style.display !== "none";
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

    if (searchInput) {
        searchInput.addEventListener("input", applyFilter);
    }

    applyFilter();
});
