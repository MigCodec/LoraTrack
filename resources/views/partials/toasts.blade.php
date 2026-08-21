    <aside class="toast-region" aria-label="Notificaciones" aria-live="polite" data-toast-region>
        @if(session('status'))
            <section class="app-toast app-toast-success" role="status" data-toast data-toast-duration="6000">
                <div class="app-toast-icon" aria-hidden="true">✓</div>
                <div class="app-toast-content">
                    <p class="app-toast-title">Acción completada</p>
                    <p class="app-toast-message">{{ session('status') }}</p>
                </div>
                <button class="app-toast-close" type="button" data-toast-close aria-label="Cerrar notificación">&times;</button>
                <span class="app-toast-progress" aria-hidden="true"></span>
            </section>
        @endif
        @if($errors->any())
            <section class="app-toast app-toast-error" role="alert" data-toast data-toast-duration="10000">
                <div class="app-toast-icon" aria-hidden="true">!</div>
                <div class="app-toast-content">
                    <p class="app-toast-title">Revisa la información ingresada</p>
                    <ul class="app-toast-list">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button class="app-toast-close" type="button" data-toast-close aria-label="Cerrar notificación">&times;</button>
                <span class="app-toast-progress" aria-hidden="true"></span>
            </section>
        @endif
    </aside>
