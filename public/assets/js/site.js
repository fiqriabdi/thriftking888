document.addEventListener("DOMContentLoaded", function() {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        if (img.complete) {
            img.style.opacity = '1';
        } else {
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.8s ease-in-out';
            img.onload = () => { img.style.opacity = '1'; };
        }
    });

    const searchToggle = document.querySelector('.search-toggle');
    const searchForm = document.getElementById('navbarSearchForm');
    if (searchToggle && searchForm) {
        searchToggle.addEventListener('click', function (event) {
            event.preventDefault();
            searchForm.classList.toggle('d-none');
            searchForm.classList.toggle('d-flex');
            if (!searchForm.classList.contains('d-none')) {
                const input = searchForm.querySelector('input[name="search"]');
                if (input) {
                    input.focus();
                }
            }
        });
    }
});

document.addEventListener('click', function (event) {
    const navbar = document.getElementById('navbarNav');
    if (navbar && navbar.classList.contains('show')) {
        const isClickInside = navbar.contains(event.target);
        if (!isClickInside) {
            const collapse = bootstrap.Collapse.getInstance(navbar);
            if (collapse) {
                collapse.hide();
            }
        }
    }
});
