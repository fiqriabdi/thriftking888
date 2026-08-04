    </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible .btn-close');
            alerts.forEach(btn => btn.addEventListener('click', () => {
                const parent = btn.closest('.alert');
                if (parent) parent.remove();
            }));
        });
    </script>
</body>
</html>
