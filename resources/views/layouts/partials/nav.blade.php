            <div class="navbar-header">
              <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#mini-menu" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </button>
              <a class="navbar-brand" href="/">
              	<img src="/medias/images/logo-corim.png" height="40" alt="Corim Immobilier" />
              </a>
            </div>
            <div class="collapse navbar-collapse" id="mini-menu">
              <ul class="nav navbar-nav">
                  @if ($sessionAcces === '1')
                  <li><a href="/gestion_programmes">Programmes</a></li>
                  <li><a href="/gestion_clients">Clients</a></li>
                  <li><a href="/gestion_prospects">Prospects</a></li>
                  <li><a href="/gestion_promoteurs">Prescripteurs</a></li>
                  <li><a href="/gestion_privilege">Privilèges</a></li>
                  <li><a href="/emailing">Emailing</a></li>
                  <li><a href="/emails">Emails</a></li>
                  <li><a href="/flash_info">Flash info</a></li>
                  <li><a href="/messages_automatiques">Messages automatiques</a></li>
                  @endif
                  <li><a href="/denonciations">Dénonciations</a></li>
                  <li><a href="/messagerie">Messagerie {!! $messageCount > 0 ? '<span class="label label-info">'.(int) $messageCount.'</span>' : '' !!}</a></li>
                  <li><a href="/contact">Contacter Corim</a></li>
                  @if ($toolbarActif)
                  <li><a href="javascript:void(0)" onclick="HideToolbar();"@if ($menuRight) class="item-menu-right"@endif>Déverrouiller les outils</a></li>
                  <li><a href="javascript:void(0)" onclick="ShowToolbar();"@if ($menuRight) class="item-menu-right"@endif>Verrouiller les outils</a></li>
                  @endif
                  <li><a href="/logout">Déconnexion</a></li>
              </ul>
            </div>
