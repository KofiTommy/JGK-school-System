document.addEventListener("DOMContentLoaded", function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll("[data-student-row]"));
    if (!rows.length) {
        return;
    }

    var searchInput = document.querySelector("[data-student-search]");
    var selectVisibleButton = document.querySelector("[data-select-visible]");
    var clearVisibleButton = document.querySelector("[data-clear-visible]");
    var toggleAll = document.querySelector("[data-toggle-all]");
    var selectedCountNode = document.querySelector("[data-selected-count]");
    var visibleCountNode = document.querySelector("[data-visible-count]");
    var disabledCountNode = document.querySelector("[data-disabled-count]");
    var emptyState = document.querySelector("[data-search-empty]");

    function rowCheckbox(row) {
        return row.querySelector("[data-student-checkbox]");
    }

    function visibleRows() {
        return rows.filter(function (row) {
            return !row.hasAttribute("hidden");
        });
    }

    function selectedVisibleCount() {
        return visibleRows().filter(function (row) {
            var checkbox = rowCheckbox(row);
            return checkbox && checkbox.checked;
        }).length;
    }

    function updateCounts() {
        var visible = visibleRows();
        var visibleCount = visible.length;
        var selectedCount = selectedVisibleCount();
        var totalVisibleCheckboxes = visible.filter(function (row) {
            return !!rowCheckbox(row);
        }).length;

        if (selectedCountNode) {
            selectedCountNode.textContent = selectedCount.toString();
        }
        if (visibleCountNode) {
            visibleCountNode.textContent = visibleCount.toString();
        }
        if (disabledCountNode) {
            disabledCountNode.textContent = Math.max(visibleCount - selectedCount, 0).toString();
        }
        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }
        if (toggleAll) {
            var allChecked = totalVisibleCheckboxes > 0 && selectedCount === totalVisibleCheckboxes;
            toggleAll.checked = allChecked;
            toggleAll.indeterminate = selectedCount > 0 && selectedCount < totalVisibleCheckboxes;
        }
    }

    function applySearch() {
        var query = searchInput ? searchInput.value.trim().toLowerCase() : "";
        rows.forEach(function (row) {
            var haystack = (row.getAttribute("data-search") || "").toLowerCase();
            row.hidden = query !== "" && haystack.indexOf(query) === -1;
        });
        updateCounts();
    }

    function setVisibleSelection(checked) {
        visibleRows().forEach(function (row) {
            var checkbox = rowCheckbox(row);
            if (checkbox) {
                checkbox.checked = checked;
            }
        });
        updateCounts();
    }

    if (searchInput) {
        searchInput.addEventListener("input", applySearch);
    }

    if (selectVisibleButton) {
        selectVisibleButton.addEventListener("click", function () {
            setVisibleSelection(true);
        });
    }

    if (clearVisibleButton) {
        clearVisibleButton.addEventListener("click", function () {
            setVisibleSelection(false);
        });
    }

    if (toggleAll) {
        toggleAll.addEventListener("change", function () {
            setVisibleSelection(toggleAll.checked);
        });
    }

    rows.forEach(function (row) {
        var checkbox = rowCheckbox(row);
        if (checkbox) {
            checkbox.addEventListener("change", updateCounts);
        }
    });

    updateCounts();
});
