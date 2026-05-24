    </div><!-- end page-content -->
</div><!-- end main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide flash messages after 4 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.flash-message .alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 4000);
</script>
</body>
</html>
