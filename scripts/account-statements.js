(function () {
    var searchInput = document.querySelector('[data-account-search]');
    if (!searchInput) {
        return;
    }

    var statementCards = Array.prototype.slice.call(document.querySelectorAll('[data-account-statement-card]'));
    var classSections = Array.prototype.slice.call(document.querySelectorAll('[data-account-class-section]'));
    var visibleCountNode = document.querySelector('[data-account-visible-count]');

    function applySearch() {
        var query = (searchInput.value || '').toLowerCase().trim();
        var visibleCount = 0;

        statementCards.forEach(function (card) {
            var haystack = (card.getAttribute('data-search') || '').toLowerCase();
            var isVisible = query === '' || haystack.indexOf(query) !== -1;
            card.hidden = !isVisible;
            if (isVisible) {
                visibleCount += 1;
            }
        });

        classSections.forEach(function (section) {
            var hasVisibleCards = Array.prototype.some.call(
                section.querySelectorAll('[data-account-statement-card]'),
                function (card) {
                    return !card.hidden;
                }
            );
            section.classList.toggle('is-hidden', !hasVisibleCards);
        });

        if (visibleCountNode) {
            visibleCountNode.textContent = String(visibleCount);
        }
    }

    searchInput.addEventListener('input', applySearch);
    applySearch();
}());
