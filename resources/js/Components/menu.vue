<script>
import { Link } from '@inertiajs/vue3';
import { resolveFooterItems, resolveMenuSections } from '@/navigation/menuItems';
import { usePermissions } from '@/composables/usePermissions';

export default {
  components: { Link },
  setup() {
    // Shared with BottomNav: one permission context means the sidebar and the
    // mobile tab bar can never disagree about what a role may reach.
    const { navigationContext, user, roles } = usePermissions();

    return { navigationContext, currentUser: user, currentRoles: roles };
  },
  data() {
    return {
      // A stored photo path is no proof the file is still on disk, so a removed
      // upload would otherwise show the browser's broken-image glyph.
      photoFailed: false,
    };
  },
  computed: {
    /**
     * Destinations grouped under their Slack-style section caption, followed by
     * the pinned group (Settings) which carries no caption and is pushed to the
     * bottom of the panel.
     */
    sections() {
      const pinned = resolveFooterItems(this.navigationContext);

      return [
        ...resolveMenuSections(this.navigationContext),
        ...(pinned.length > 0 ? [{ key: '__pinned', labelKey: null, items: pinned }] : []),
      ];
    },
    roleLabel() {
      return this.currentUser?.role_label ?? this.currentRoles[0] ?? '';
    },
    userInitials() {
      const user = this.currentUser;

      if (!user) {
        return '?';
      }

      const first = (user.first_name || user.name || '?').charAt(0);
      const last = (user.last_name || '').charAt(0);

      return (first + last).toUpperCase();
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
    <!-- Identity and presence at the top of the panel, as in Slack: who you are
         signed in as is the first thing to confirm when a workspace has several
         roles with very different permissions. -->
    <Link :href="route('profile.show')" class="menu-identity">
      <span class="menu-identity-avatar">
        <img
          v-if="currentUser?.photo_url && !photoFailed"
          :src="currentUser.photo_url"
          :alt="currentUser.full_name"
          @error="photoFailed = true"
        />
        <span v-else>{{ userInitials }}</span>
      </span>
      <span class="menu-identity-text">
        <span class="menu-identity-name">{{ currentUser?.full_name }}</span>
        <span class="menu-identity-role">
          <span class="menu-identity-presence" aria-hidden="true"></span>
          {{ roleLabel }}
        </span>
      </span>
    </Link>

    <ul class="navbar-nav h-100 d-flex flex-column" id="navbar-nav">
      <template v-for="section in sections" :key="section.key">
        <li v-if="section.labelKey" class="menu-section-title">{{ $t(section.labelKey) }}</li>

        <li
          v-for="(item, index) in section.items"
          :key="item.key"
          class="nav-item"
          :class="{ 'mt-auto': item.footer && index === 0 }"
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
      </template>
    </ul>
  </BContainer>
</template>

<style scoped>
.menu-identity {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0.5rem 0 0.25rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.6rem;
  color: var(--vz-vertical-menu-item-color);
  text-decoration: none;
  transition: background-color 0.15s ease;
}

.menu-identity:hover {
  background-color: var(--vz-vertical-menu-item-active-bg);
  color: var(--vz-vertical-menu-item-active-color);
}

.menu-identity-avatar {
  display: inline-flex;
  overflow: hidden;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  background-color: var(--vz-vertical-menu-item-active-bg);
  font-size: 0.7rem;
  font-weight: 600;
}

.menu-identity-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.menu-identity-text {
  min-width: 0;
}

.menu-identity-name,
.menu-identity-role {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.menu-identity-name {
  font-size: 0.8125rem;
  font-weight: 600;
}

.menu-identity-role {
  color: var(--vz-vertical-menu-title-color);
  font-size: 0.6875rem;
}

.menu-identity-presence {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  margin-right: 0.25rem;
  border-radius: 50%;
  background-color: #0ab39c;
  vertical-align: middle;
}

/* Collapsed sidebar shows icons only; the identity block has no room there. */
[data-sidebar-size='sm'] .menu-identity,
[data-sidebar-size='sm-hover'] .menu-identity {
  display: none;
}
</style>
