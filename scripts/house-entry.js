document.addEventListener("DOMContentLoaded", function () {
    var input = document.querySelector("[data-house-search]");
    if (!input) {
        return;
    }

    var rows = Array.prototype.slice.call(document.querySelectorAll("[data-house-row]"));
    var emptyState = document.querySelector("[data-house-empty]");

    function applyFilter() {
        var query = input.value.toLowerCase().trim();
        var visibleCount = 0;

        rows.forEach(function (row) {
            var searchText = (row.getAttribute("data-search") || "").toLowerCase();
            var match = query === "" || searchText.indexOf(query) !== -1;
            row.hidden = !match;
            if (match) {
                visibleCount++;
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }
    }

    input.addEventListener("input", applyFilter);
    applyFilter();
});
