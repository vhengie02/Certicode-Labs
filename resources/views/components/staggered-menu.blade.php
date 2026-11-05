@props(['items', 'user'])

{{-- The app's whole menu: page links from the layout, then the account's Settings and Sign out. --}}
<div class="staggered-menu" x-data="staggeredMenu" :data-open="open || null"
     @click.outside="close()" @keydown.escape.window="close()">
    <button type="button" class="sm-toggle" x-ref="toggle" @click="toggle()"
            aria-label="Open menu" :aria-label="open ? 'Close menu' : 'Open menu'"
            aria-expanded="false" :aria-expanded="open.toString()" aria-controls="staggered-menu-panel">
        <span class="sm-toggle-textWrap" aria-hidden="true">
            <span class="sm-toggle-textInner" x-ref="label">
                <span class="sm-toggle-line">Menu</span>
                <span class="sm-toggle-line">Close</span>
            </span>
        </span>
        <span class="sm-icon" x-ref="icon" aria-hidden="true">
            <span class="sm-icon-line"></span>
            <span class="sm-icon-line sm-icon-line-v"></span>
        </span>
    </button>

    <div class="sm-drawer" x-ref="drawer">
        <div class="sm-prelayer" aria-hidden="true"></div>
        <div class="sm-prelayer" aria-hidden="true"></div>

        <nav id="staggered-menu-panel" class="staggered-menu-panel" x-ref="panel" aria-label="Primary" inert>
            <ul class="sm-panel-list">
                @foreach ($items as $item)
                    <li class="sm-panel-itemWrap">
                        <a href="{{ $item['url'] }}" class="sm-panel-item" @if ($item['active']) aria-current="page" @endif>
                            <span class="sm-panel-itemLabel">
                                {{ $item['label'] }}
                                @if (! empty($item['badge']))
                                    <span class="sm-panel-badge" aria-label="{{ $item['badge'] }} pending">{{ $item['badge'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach

                <li class="sm-panel-itemWrap sm-panel-itemWrap--account">
                    <a href="{{ route('settings.show') }}" class="sm-panel-item" @if (request()->routeIs('settings.*')) aria-current="page" @endif>
                        <span class="sm-panel-itemLabel">Settings</span>
                    </a>
                </li>
                <li class="sm-panel-itemWrap">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sm-panel-item sm-panel-item--danger">
                            <span class="sm-panel-itemLabel">Sign out</span>
                        </button>
                    </form>
                </li>
            </ul>

            <div class="sm-panel-footer">
                <span class="sm-panel-avatar" aria-hidden="true">{{ strtoupper(\Illuminate\Support\Str::substr($user->name, 0, 2)) }}</span>
                <div class="sm-panel-identity">
                    <p class="sm-panel-name">{{ $user->name }}</p>
                    <p class="sm-panel-email">{{ $user->email }}</p>
                </div>
                <span class="sm-panel-role">{{ $user->role }}</span>
            </div>
        </nav>
    </div>
</div>
