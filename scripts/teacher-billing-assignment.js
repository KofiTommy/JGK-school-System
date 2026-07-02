document.addEventListener("DOMContentLoaded", function () {
    function attachFilter(inputSelector, rowSelector, emptySelector) {
        var input = document.querySelector(inputSelector);
        if (!input) {
            return;
        }

        var rows = Array.prototype.slice.call(document.querySelectorAll(rowSelector));
        var emptyState = document.querySelector(emptySelector);

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
    }

    attachFilter("[data-assignment-search]", "[data-assignment-row]", "[data-assignment-empty]");
    attachFilter("[data-item-search]", "[data-item-card]", "[data-item-empty]");
});
