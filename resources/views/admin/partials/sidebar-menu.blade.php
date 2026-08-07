
@foreach ($menus as $menu)
    @php
        // Check if this parent menu should be open
        $isActiveParent = false;

        // Check parent route wildcard
        if (!empty($menu->route) && request()->is(trim($menu->route, '/') . '*')) {
            $isActiveParent = true;
        }

        // Check if any child (recursively) is active
        if (!$isActiveParent && $menu->children->isNotEmpty()) {
            foreach ($menu->children as $child) {
                if (!empty($child->route) && request()->is(trim($child->route, '/') . '*')) {
                    $isActiveParent = true;
                    break;
                }
            }
        }
    @endphp

    <li class="nav-item {{ $isActiveParent ? 'menu-open' : '' }}">
        <a href="{{ $menu->route ? url($menu->route) : '#' }}" 
           class="nav-link {{ $isActiveParent ? 'active' : '' }}">
            <i class="nav-icon fas {{ $menu->icon }}"></i>
            <p>
                {{ $menu->name }}
                @if ($menu->children->isNotEmpty())
                    <i class="fas fa-angle-left right"></i>
                @endif
            </p>
        </a>

        @if ($menu->children->isNotEmpty())
            <ul class="nav nav-treeview">
                @foreach ($menu->children as $child)
                    @if ($child->children->isEmpty())
                        {{-- Child Menu (Exact Match) --}}
                        <li class="nav-item">
                            <a href="{{ url($child->route) }}"
                               class="nav-link {{ request()->is(trim($child->route, '/')) ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>{{ $child->name }}</p>
                            </a>
                        </li>
                    @else
                        {{-- Recursive call for sub-children --}}
                        @include('admin.partials.sidebar-menu', ['menus' => collect([$child])])
                    @endif
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
