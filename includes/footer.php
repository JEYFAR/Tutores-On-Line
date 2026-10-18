<?php
/**
 * =============================================================================
 * TutoresOnLine - Pie de Página Común (Footer)
 * Cierra contenedor principal, pie de página, Bootstrap JS y scripts extra.
 * Compatible con PHP 7.4+
 * =============================================================================
 */
?>
</main>

<footer class="footer-custom mt-auto">
    <div class="container py-3">
        <div class="row align-items-center">
            <div class="col-md-6 text-md-start mb-2 mb-md-0">
                <span class="fw-bold text-dark">TutoresOnLine</span> &copy; <?= date('Y') ?> &mdash; La red de tutorías académicas personalizadas.
            </div>
            <div class="col-md-6 text-md-end">
                <ul class="list-inline mb-0 small">
                    <li class="list-inline-item"><a href="<?= APP_URL ?>/buscar.php" class="text-secondary text-decoration-none">Buscar tutores</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><a href="<?= APP_URL ?>/registro.php?rol=tutor" class="text-secondary text-decoration-none">Ser tutor</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><span class="text-secondary">Términos y Privacidad</span></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS CDN (con Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Scripts adicionales específicos de la vista -->
<?php if (!empty($scripts_extra)): ?>
    <?= $scripts_extra ?>
<?php endif; ?>

</body>
</html>
