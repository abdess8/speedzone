<script>
import { Link } from '@inertiajs/vue3';

export default {
  components: { Link },
  computed: {
    auth() {
      return {
        permissions: this.$page.props.permissions ?? [],
        isSuperAdmin: this.$page.props.isSuperAdmin ?? false,
      };
    },
    showDeliveryZones() {
      return this.canViewSectors() || this.canViewDriverZones();
    },
  },
  mounted() {
    this.initActiveMenu();
    this.onRoutechange();
    this.bindCollapseHandlers();
  },
  methods: {
    hasPermission(permission) {
      if (this.auth.isSuperAdmin) {
        return true;
      }

      return (this.auth.permissions ?? []).includes(permission);
    },
    canViewOrders() {
      return this.hasPermission('orders.read.all') || this.hasPermission('orders.read.own');
    },
    canViewPickups() {
      return (
        this.hasPermission('pickup_requests.read.all') ||
        this.hasPermission('pickup_requests.read.own') ||
        this.hasPermission('pickup_requests.read.assigned')
      );
    },
    canViewTransfers() {
      return this.hasPermission('transfers.read') || this.hasPermission('transfers.read.assigned');
    },
    isSeller() {
      const user = this.$page.props.auth?.user;
      if (!user) {
        return false;
      }

      if (user.is_seller === true) {
        return true;
      }

      return (user.roles ?? []).includes('Seller');
    },
    canViewReturns() {
      const user = this.$page.props.auth?.user;
      if (user?.can_view_returns === true) {
        return true;
      }

      return (
        this.isSeller() ||
        this.hasPermission('returns.read.all') ||
        this.hasPermission('returns.read.own') ||
        this.hasPermission('returns.create_request') ||
        this.hasPermission('returns.update_status') ||
        this.hasPermission('returns.create') ||
        this.hasPermission('returns.manage')
      );
    },
    canViewInvoices() {
      return this.hasPermission('invoices.read.all') || this.hasPermission('invoices.read.own');
    },
    canManageInvoices() {
      return this.hasPermission('invoices.read.all');
    },
    canViewSectors() {
      return this.hasPermission('sectors.read');
    },
    canViewDriverZones() {
      return this.hasPermission('driver_zones.read');
    },
    canViewUsers() {
      return this.hasPermission('roles.read');
    },
    canViewRoles() {
      return this.hasPermission('roles.read');
    },
    canViewCities() {
      return this.hasPermission('cities.read');
    },
    canViewApiIntegrations() {
      return this.hasPermission('permissions.read') || this.hasPermission('roles.read');
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

      <li class="nav-item">
        <Link href="/" class="nav-link menu-link">
          <i class="ri-dashboard-2-line"></i>
          <span>{{ $t('sidebar.dashboard') }}</span>
        </Link>
      </li>

      <li v-if="canViewOrders()" class="nav-item">
        <Link href="/orders" class="nav-link menu-link">
          <i class="ri-shopping-basket-2-line"></i>
          <span>{{ $t('sidebar.orders') }}</span>
        </Link>
      </li>

      <li v-if="canViewPickups()" class="nav-item">
        <Link href="/pickup-requests" class="nav-link menu-link">
          <i class="ri-truck-line"></i>
          <span>{{ $t('sidebar.pickups') }}</span>
        </Link>
      </li>

      <li v-if="canViewTransfers()" class="nav-item">
        <Link href="/transfers" class="nav-link menu-link">
          <i class="ri-route-line"></i>
          <span>{{ $t('sidebar.transfers') }}</span>
        </Link>
      </li>

      <li v-if="canViewReturns()" class="nav-item">
        <Link href="/returns" class="nav-link menu-link">
          <i class="ri-arrow-go-back-line"></i>
          <span>{{ $t('sidebar.returns') }}</span>
        </Link>
      </li>

      <li v-if="canViewInvoices()" class="nav-item">
        <a
          class="nav-link menu-link"
          href="#sidebarBilling"
          data-bs-toggle="collapse"
          role="button"
          aria-expanded="false"
          aria-controls="sidebarBilling"
        >
          <i class="ri-bill-line"></i>
          <span>{{ $t('sidebar.invoices') }}</span>
        </a>
        <div class="collapse menu-dropdown" id="sidebarBilling">
          <ul class="nav nav-sm flex-column">
            <li class="nav-item">
              <Link href="/invoices" class="nav-link">{{ $t('sidebar.invoices') }}</Link>
            </li>
            <li class="nav-item">
              <Link href="/invoices/pending" class="nav-link">{{ $t('sidebar.pending_billing') }}</Link>
            </li>
          </ul>
        </div>
      </li>

      <li v-if="showDeliveryZones" class="nav-item">
        <a
          class="nav-link menu-link"
          href="#sidebarZones"
          data-bs-toggle="collapse"
          role="button"
          aria-expanded="false"
          aria-controls="sidebarZones"
        >
          <i class="ri-map-pin-line"></i>
          <span>{{ $t('sidebar.delivery_zones') }}</span>
        </a>
        <div class="collapse menu-dropdown" id="sidebarZones">
          <ul class="nav nav-sm flex-column">
            <li v-if="canViewSectors()" class="nav-item">
              <Link href="/sectors" class="nav-link">{{ $t('sidebar.sectors') }}</Link>
            </li>
            <li v-if="canViewDriverZones()" class="nav-item">
              <Link href="/driver-zones" class="nav-link">{{ $t('sidebar.driver_zones') }}</Link>
            </li>
          </ul>
        </div>
      </li>

      <li class="menu-title mt-auto">
        <span>{{ $t('sidebar.settings') }}</span>
      </li>

      <li class="nav-item">
        <Link :href="route('profile.show')" class="nav-link menu-link">
          <i class="ri-user-settings-line"></i>
          <span>{{ $t('sidebar.profile') }}</span>
        </Link>
      </li>

      <li v-if="canViewUsers()" class="nav-item">
        <Link href="/users" class="nav-link menu-link">
          <i class="ri-account-circle-line"></i>
          <span>{{ $t('sidebar.users') }}</span>
        </Link>
      </li>

      <li v-if="canViewRoles()" class="nav-item">
        <Link href="/roles" class="nav-link menu-link">
          <i class="ri-shield-keyhole-line"></i>
          <span>{{ $t('sidebar.roles_permissions') }}</span>
        </Link>
      </li>

      <li v-if="canViewCities()" class="nav-item">
        <Link href="/cities" class="nav-link menu-link">
          <i class="ri-map-pin-line"></i>
          <span>{{ $t('sidebar.cities') }}</span>
        </Link>
      </li>

      <li v-if="canViewApiIntegrations()" class="nav-item">
        <Link :href="route('api-integrations.index')" class="nav-link menu-link">
          <i class="ri-plug-2-line"></i>
          <span>{{ $t('sidebar.api_integrations') }}</span>
        </Link>
      </li>
    </ul>
  </BContainer>
</template>
