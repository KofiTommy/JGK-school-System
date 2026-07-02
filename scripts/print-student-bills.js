document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.querySelector("[data-student-search]");
    var termSelect = document.querySelector("[data-bulk-term]");
    var selectVisibleButton = document.querySelector("[data-select-visible]");
    var clearVisibleButton = document.querySelector("[data-clear-visible]");
    var selectedCountNode = document.querySelector("[data-selected-count]");
    var emptyState = document.querySelector("[data-student-empty]");

    var cards = Array.prototype.slice.call(document.querySelectorAll("[data-student-card]"));

    function visibleCards() {
        return cards.filter(function (card) {
            return !card.hidden;
        });
    }

    function applySearch() {
        if (!searchInput) {
            return;
        }
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

    function applyTermEligibility() {
        var selectedTerm = termSelect ? termSelect.value.trim() : "";

        cards.forEach(function (card) {
            var checkbox = card.querySelector("[data-student-checkbox]");
            var printableTerms = (card.getAttribute("data-printable-terms") || "")
                .split(",")
                .map(function (value) {
                    return value.trim();
                })
                .filter(function (value) {
                    return value !== "";
                });

            var eligible = selectedTerm === "" || printableTerms.indexOf(selectedTerm) !== -1;
            card.classList.toggle("psb-student-card--term-mismatch", !eligible && selectedTerm !== "");

            if (checkbox) {
                checkbox.disabled = !eligible;
                if (!eligible) {
                    checkbox.checked = false;
                }
            }
        });
    }

    function updateSelectedCount() {
        if (!selectedCountNode) {
            return;
        }
        var selected = document.querySelectorAll("[data-student-checkbox]:checked").length;
        selectedCountNode.textContent = selected + " student" + (selected === 1 ? "" : "s") + " selected";
    }

    function selectVisible(flag) {
        visibleCards().forEach(function (card) {
            var checkbox = card.querySelector("[data-student-checkbox]");
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = flag;
            }
        });
        updateSelectedCount();
    }

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            applySearch();
        });
    }

    if (termSelect) {
        termSelect.addEventListener("change", function () {
            applyTermEligibility();
            updateSelectedCount();
        });
    }

    if (selectVisibleButton) {
        selectVisibleButton.addEventListener("click", function () {
            selectVisible(true);
        });
    }

    if (clearVisibleButton) {
        clearVisibleButton.addEventListener("click", function () {
            selectVisible(false);
        });
    }

    document.addEventListener("change", function (event) {
        if (event.target && event.target.matches("[data-student-checkbox]")) {
            updateSelectedCount();
        }
    });

    applySearch();
    applyTermEligibility();
    updateSelectedCount();
});
