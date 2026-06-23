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
    showSettings() {
      return true;
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
      return (
        this.hasPermission('orders.read.all')
        || this.hasPermission('orders.read.own')
        || this.hasPermission('orders.read.assigned')
      );
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
    canManageDriverInvoices() {
      return this.hasPermission('driver_invoices.read.all');
    },
    canViewDriverFinance() {
      return this.hasPermission('driver_invoices.read.own') && !this.hasPermission('driver_invoices.read.all');
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
    canViewPartners() {
      return this.hasPermission('partners.read');
    },
    canViewPartnerOrders() {
      return (
        this.hasPermission('partners.read') ||
        this.hasPermission('partners.deliveries.manage') ||
        (this.$page.props.auth?.user?.roles ?? []).includes('Driver')
      );
    },
    canViewPartnerAssignments() {
      return this.hasPermission('partners.update');
    },
    canViewApiIntegrations() {
      return this.hasPermission('permissions.read') || this.hasPermission('roles.read');
    },
    canViewSupport() {
      return (
        this.hasPermission('support.read.all') ||
        this.hasPermission('support.read.own') ||
        this.hasPermission('support.manage')
      );
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

      <li v-if="canViewPartnerOrders()" class="nav-item">
        <Link href="/partner-orders" class="nav-link menu-link">
          <i class="ri-links-line"></i>
          <span>{{ $t('sidebar.partner_orders') }}</span>
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

      <li v-if="canManageDriverInvoices" class="nav-item">
        <a
          class="nav-link menu-link"
          href="#sidebarDriverBilling"
          data-bs-toggle="collapse"
          role="button"
          aria-expanded="false"
          aria-controls="sidebarDriverBilling"
        >
          <i class="ri-e-bike-2-line"></i>
          <span>{{ $t('sidebar.driver_billing') }}</span>
        </a>
        <div class="collapse menu-dropdown" id="sidebarDriverBilling">
          <ul class="nav nav-sm flex-column">
            <li class="nav-item">
              <Link href="/driver-invoices" class="nav-link">{{ $t('sidebar.driver_invoices') }}</Link>
            </li>
            <li class="nav-item">
              <Link href="/driver-invoices/pending" class="nav-link">{{ $t('sidebar.driver_pending_billing') }}</Link>
            </li>
            <li class="nav-item">
              <Link href="/driver-invoices/payments" class="nav-link">{{ $t('sidebar.driver_payments') }}</Link>
            </li>
          </ul>
        </div>
      </li>

      <li v-if="canViewDriverFinance" class="nav-item">
        <Link href="/driver-finance" class="nav-link menu-link">
          <i class="ri-wallet-3-line"></i>
          <span>{{ $t('sidebar.driver_finance') }}</span>
        </Link>
      </li>

      <li v-if="canViewSupport()" class="nav-item">
        <Link href="/support-tickets" class="nav-link menu-link">
          <i class="ri-customer-service-2-line"></i>
          <span>{{ $t('sidebar.support') }}</span>
        </Link>
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

      <li v-if="showSettings" class="nav-item mt-auto">
        <a
          class="nav-link menu-link"
          href="#sidebarSettings"
          data-bs-toggle="collapse"
          role="button"
          aria-expanded="false"
          aria-controls="sidebarSettings"
        >
          <i class="ri-settings-3-line"></i>
          <span>{{ $t('sidebar.settings.title') }}</span>
        </a>
        <div class="collapse menu-dropdown" id="sidebarSettings">
          <ul class="nav nav-sm flex-column">
            <li class="nav-item">
              <Link :href="route('profile.show')" class="nav-link">{{ $t('sidebar.settings.profile') }}</Link>
            </li>
            <li v-if="canViewUsers()" class="nav-item">
              <Link href="/users" class="nav-link">{{ $t('sidebar.settings.users') }}</Link>
            </li>
            <li v-if="canViewRoles()" class="nav-item">
              <Link href="/roles" class="nav-link">{{ $t('sidebar.settings.roles_permissions') }}</Link>
            </li>
            <li v-if="canViewCities()" class="nav-item">
              <Link href="/cities" class="nav-link">{{ $t('sidebar.settings.cities') }}</Link>
            </li>
            <li v-if="canViewPartners()" class="nav-item">
              <Link href="/partners" class="nav-link">{{ $t('sidebar.settings.partners') }}</Link>
            </li>
            <li v-if="canViewPartnerAssignments()" class="nav-item">
              <Link :href="route('partner-assignments.index')" class="nav-link">{{ $t('sidebar.settings.partner_assignments') }}</Link>
            </li>
            <li v-if="canViewApiIntegrations()" class="nav-item">
              <Link :href="route('api-integrations.index')" class="nav-link">{{ $t('sidebar.settings.api_integrations') }}</Link>
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </BContainer>
</template>
