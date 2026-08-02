export function applyLayoutAttributes(layout) {
  const el = document.documentElement;

  el.setAttribute('data-layout', layout.layoutType ?? 'vertical');
  el.setAttribute('data-layout-width', layout.layoutWidth ?? 'fluid');
  el.setAttribute('data-layout-position', layout.position ?? 'fixed');
  el.setAttribute('data-topbar', layout.topbar ?? 'light');
  el.setAttribute('data-sidebar-size', layout.sidebarSize ?? 'lg');
  el.setAttribute('data-sidebar', layout.sidebarColor ?? 'dark');
  el.setAttribute('data-sidebar-image', layout.sidebarImage ?? 'none');
  el.setAttribute('data-preloader', layout.preloader ?? 'disable');
  el.setAttribute('data-sidebar-visibility', layout.visibility ?? 'show');
  el.setAttribute('data-bs-theme', layout.mode ?? 'light');
}
