document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("continuing-search");
    var rows = Array.prototype.slice.call(document.querySelectorAll(".continuing-row"));
    var visibleCount = document.getElementById("continuing-visible-count");
    var emptyState = document.getElementById("continuing-search-empty");

    if (!searchInput || rows.length === 0 || !visibleCount || !emptyState) {
        return;
    }

    function applySearch() {
        var query = searchInput.value.toLowerCase().trim();
        var visible = 0;

        rows.forEach(function (row) {
            var haystack = (row.getAttribute("data-search") || "").toLowerCase();
            var match = query === "" || haystack.indexOf(query) !== -1;
            row.hidden = !match;
            if (match) {
                visible += 1;
            }
        });

        visibleCount.textContent = String(visible);
        emptyState.hidden = visible !== 0;
    }

    searchInput.addEventListener("input", applySearch);
});
