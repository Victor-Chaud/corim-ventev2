            <div class="navbar-header">
              <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#mini-menu" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </button>
              <button type="button" class="theme-toggle" title="Mode sombre" aria-label="Mode sombre">
                <svg class="moon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1"/></g></svg>
              </button>
              <a class="navbar-brand" href="/">
              	<img src="/medias/images/logo-corim.png" height="40" alt="Corim Immobilier" />
              </a>
            </div>
            <div class="collapse navbar-collapse" id="mini-menu">
              <ul class="nav navbar-nav">
                  @if ($sessionAccess === '1')
                  <li><a href="/gestion_programmes">Programmes</a></li>
                  <li><a href="/gestion_promoteurs">Prescripteurs</a></li>
                  <li><a href="/gestion_privilege">Privilèges</a></li>
                  <li><a href="/emails">Emails</a></li>
                  <li><a href="/flash_info">Flash info</a></li>
                  <li><a href="/messages_automatiques">Messages automatiques</a></li>
                  @endif
                  <li><a href="/denonciations">Dénonciations</a></li>
                  <li><a href="/messagerie">Messagerie {!! $messageCount > 0 ? '<span class="label label-info">'.(int) $messageCount.'</span>' : '' !!}</a></li>
                  <li><a href="/contact">Contacter Corim</a></li>
                  @if ($toolbarActive)
                  <li><a href="javascript:void(0)" onclick="HideToolbar();"@if ($menuRight) class="item-menu-right"@endif>Déverrouiller les outils</a></li>
                  <li><a href="javascript:void(0)" onclick="ShowToolbar();"@if ($menuRight) class="item-menu-right"@endif>Verrouiller les outils</a></li>
                  @endif
                  <li><a href="/logout">Déconnexion</a></li>
              </ul>
            </div>
