document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.querySelector("[data-student-search]");
    if (!searchInput) {
        return;
    }

    var cards = Array.prototype.slice.call(document.querySelectorAll("[data-student-card]"));
    var emptyState = document.querySelector("[data-student-empty]");

    function applyFilter() {
        var query = searchInput.value.toLowerCase().trim();
        var visibleCount = 0;

        cards.forEach(function (card) {
            var searchText = (card.getAttribute("data-search") || "").toLowerCase();
            var match = query === "" || searchText.indexOf(query) !== -1;
            card.hidden = !match;
            if (match) {
                visibleCount++;
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }
    }

    searchInput.addEventListener("input", applyFilter);
    applyFilter();
});
