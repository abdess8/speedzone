<script>
import { Link } from '@inertiajs/vue3';
import { resolveMenuItems } from '@/navigation/menuItems';

export default {
  components: { Link },
  computed: {
    /**
     * Permission context handed to the menu definition. Kept as plain values so
     * `menuItems.js` stays free of Vue reactivity concerns and is unit testable.
     */
    permissionContext() {
      const permissions = this.$page.props.permissions ?? [];
      const isSuperAdmin = this.$page.props.isSuperAdmin === true;
      const user = this.$page.props.auth?.user ?? null;
      const roles = user?.roles ?? [];

      const can = (permission) => isSuperAdmin || permissions.includes(permission);

      return {
        can,
        canAny: (candidates) => [candidates].flat().filter(Boolean).some(can),
        isSuperAdmin,
        user,
        roles,
        isDriver: roles.includes('Driver'),
        isSeller: user?.is_seller === true || roles.includes('Seller'),
      };
    },
    visibleItems() {
      return resolveMenuItems(this.permissionContext);
    },
  },
  mounted() {
    this.initActiveMenu();
    this.onRoutechange();
    this.bindCollapseHandlers();
  },
  methods: {
    /**
     * Ziggy route names are resolved lazily: a name belonging to a route the user
     * cannot reach still exists in the Ziggy manifest, but the entry itself is
     * already filtered out by then.
     */
    itemHref(item) {
      return item.route ? this.route(item.route) : item.href;
    },
    bindCollapseHandlers() {
      if (!document.querySelectorAll('.navbar-nav .collapse')) {
        return;
      }

      document.querySelectorAll('.navbar-nav .collapse').forEach((collapse) => {
        collapse.addEventListener('show.bs.collapse', (e) => {
          e.stopPropagation();
          const closestCollapse = collapse.parentElement.closest('.collapse');
          if (closestCollapse) {
            closestCollapse.querySelectorAll('.collapse').forEach((siblingCollapse) => {
              if (siblingCollapse.classList.contains('show')) {
                siblingCollapse.classList.remove('show');
                siblingCollapse.parentElement.firstChild.setAttribute('aria-expanded', 'false');
              }
            });
          } else {
            const getSiblings = (elem) => {
              const siblings = [];
              let sibling = elem.parentNode.firstChild;
              while (sibling) {
                if (sibling.nodeType === 1 && sibling !== elem) {
                  siblings.push(sibling);
                }
                sibling = sibling.nextSibling;
              }
              return siblings;
            };
            getSiblings(collapse.parentElement).forEach((item) => {
              if (item.childNodes.length > 2) {
                item.firstElementChild.setAttribute('aria-expanded', 'false');
                item.firstElementChild.classList.remove('active');
              }
              item.querySelectorAll('*[id]').forEach((item1) => {
                item1.classList.remove('show');
                item1.parentElement.firstChild.setAttribute('aria-expanded', 'false');
                item1.parentElement.firstChild.classList.remove('active');
              });
            });
          }
        });

        collapse.addEventListener('hide.bs.collapse', (e) => {
          e.stopPropagation();
          collapse.querySelectorAll('.collapse').forEach((childCollapse) => {
            childCollapse.classList.remove('show');
            childCollapse.parentElement.firstChild.setAttribute('aria-expanded', 'false');
          });
        });
      });
    },
    onRoutechange() {
      setTimeout(() => {
        const currentPath = window.location.pathname + window.location.search;
        const pathOnly = window.location.pathname;
        const nav = document.querySelector('#navbar-nav');
        if (!nav) {
          return;
        }

        let link = nav.querySelector(`[href="${currentPath}"]`) ?? nav.querySelector(`[href="${pathOnly}"]`);
        if (!link && pathOnly === '/') {
          link = nav.querySelector('[href="/"]');
        }

        const offsetTop = link?.offsetTop ?? 0;
        const wrapper = document.querySelector('#scrollbar .simplebar-content-wrapper');
        if (offsetTop > document.documentElement.clientHeight && wrapper) {
          wrapper.scrollTop = offsetTop + 300;
        }
      }, 500);
    },
    initActiveMenu() {
      setTimeout(() => {
        const currentPath = window.location.pathname;
        const nav = document.querySelector('#navbar-nav');
        if (!nav) {
          return;
        }

        let link = nav.querySelector(`[href="${currentPath}"]`);
        if (!link && window.location.search) {
          link = nav.querySelector(`[href="${currentPath}${window.location.search}"]`);
        }
        if (!link && currentPath === '/') {
          link = nav.querySelector('[href="/"]');
        }

        if (!link) {
          return;
        }

        link.classList.add('active');
        let parentCollapseDiv = link.closest('.collapse.menu-dropdown');
        while (parentCollapseDiv) {
          parentCollapseDiv.classList.add('show');
          const trigger = parentCollapseDiv.parentElement?.children?.[0];
          if (trigger) {
            trigger.classList.add('active');
            trigger.setAttribute('aria-expanded', 'true');
          }
          parentCollapseDiv = parentCollapseDiv.parentElement?.closest('.collapse.menu-dropdown');
        }
      }, 0);
    },
  },
};
</script>

<template>
  <BContainer fluid>
    <ul class="navbar-nav h-100 d-flex flex-column" id="navbar-nav">
      <li class="menu-title">
        <span>{{ $t('sidebar.menu') }}</span>
      </li>

      <li
        v-for="item in visibleItems"
        :key="item.key"
        class="nav-item"
        :class="{ 'mt-auto': item.footer }"
      >
        <Link v-if="!item.children" :href="itemHref(item)" class="nav-link menu-link">
          <i :class="item.icon"></i>
          <span>{{ $t(item.labelKey) }}</span>
        </Link>

        <template v-else>
          <a
            class="nav-link menu-link"
            :href="`#sidebar-${item.key}`"
            data-bs-toggle="collapse"
            role="button"
            aria-expanded="false"
            :aria-controls="`sidebar-${item.key}`"
          >
            <i :class="item.icon"></i>
            <span>{{ $t(item.labelKey) }}</span>
          </a>
          <div class="collapse menu-dropdown" :id="`sidebar-${item.key}`">
            <ul class="nav nav-sm flex-column">
              <li v-for="child in item.children" :key="child.key" class="nav-item">
                <Link :href="itemHref(child)" class="nav-link">{{ $t(child.labelKey) }}</Link>
              </li>
            </ul>
          </div>
        </template>
      </li>
    </ul>
  </BContainer>
</template>
