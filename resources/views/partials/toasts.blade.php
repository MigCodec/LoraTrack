@if(session('status') || $errors->any())
    <aside class="toast-region" aria-label="Notificaciones">
        @if(session('status'))
            <section class="app-toast app-toast-success" role="status" data-toast>
                <div class="app-toast-icon" aria-hidden="true">OK</div>
                <div class="app-toast-content">
                    <p class="app-toast-title">Accion completada</p>
                    <p class="app-toast-message">{{ session('status') }}</p>
                </div>
                <button class="app-toast-close" type="button" data-toast-close aria-label="Cerrar notificacion">&times;</button>
            </section>
        @endif
        @if($errors->any())
            <section class="app-toast app-toast-error" role="alert" data-toast>
                <div class="app-toast-icon" aria-hidden="true">!</div>
                <div class="app-toast-content">
                    <p class="app-toast-title">Revisa la informacion ingresada</p>
                    <ul class="app-toast-list">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button class="app-toast-close" type="button" data-toast-close aria-label="Cerrar notificacion">&times;</button>
            </section>
        @endif
    </aside>
@endif
