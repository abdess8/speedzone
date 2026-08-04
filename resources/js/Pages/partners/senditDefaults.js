/**
 * Default Sendit partner configuration and status mapping (Sendit → OWL Delivery).
 */
export const SENDIT_STATUS_MAPPINGS = [
  { speedzone_status: "IN_TRANSIT", partner_status: "En transit" },
  { speedzone_status: "RECEIVED_IN_DESTINATION", partner_status: "Distribue" },
  { speedzone_status: "OUT_FOR_DELIVERY", partner_status: "En cours de livraison" },
  { speedzone_status: "DELIVERED", partner_status: "livree" },
  { speedzone_status: "FAILED", partner_status: "en attente de retour" },
  { speedzone_status: "REJECTED", partner_status: "Rejeté" },
  { speedzone_status: "CANCELED", partner_status: "Annulé" },
];

export const SENDIT_FIELD_MAPPINGS = [
  { speedzone_field: "external_tracking_code", partner_field: "code" },
  { speedzone_field: "status", partner_field: "status" },
  { speedzone_field: "customer_name", partner_field: "name" },
  { speedzone_field: "customer_phone", partner_field: "phone" },
  { speedzone_field: "order_amount", partner_field: "amount" },
  { speedzone_field: "notes", partner_field: "note" },
  { speedzone_field: "city_name", partner_field: "district" },
  { speedzone_field: "customer_address", partner_field: "address" },
  { speedzone_field: "option_exchange", partner_field: "option_exchange" },
];

export const SENDIT_UPDATE_FIELD_MAPPINGS = [
  { speedzone_field: "external_tracking_code", partner_field: "id" },
  { speedzone_field: "partner_status", partner_field: "status" },
  { speedzone_field: "status_comment", partner_field: "message" },
  { speedzone_field: "proof_image", partner_field: "proof_image" },
  { speedzone_field: "delivered_at", partner_field: "deliver_by" },
  { speedzone_field: "is_delivered_partial", partner_field: "isDeliveredPartial" },
];

export const senditPartnerDefaults = {
  name: "Sendit",
  api_base_url: "https://app.sendit.ma/api/v1",
  auth_type: "LOGIN_TOKEN",
  endpoint_login: "https://app.sendit.ma/api/v1/login",
  login_username_field: "public_key",
  login_password_field: "secret_key",
  login_token_field: "data.token",
  endpoint_statuses: "/all-status-deliveries",
  endpoint_deliveries: "/deliveries",
  delivery_lookup_param: "code-delivery",
  endpoint_update: "/update-deliveries",
  status_mappings: SENDIT_STATUS_MAPPINGS,
  field_mappings: SENDIT_FIELD_MAPPINGS,
  update_field_mappings: SENDIT_UPDATE_FIELD_MAPPINGS,
};
