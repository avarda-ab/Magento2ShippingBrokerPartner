# Avarda_ShippingBrokerPartner

Avarda Partner Shipping provider for `Avarda_ShippingBroker`, with Magento acting
as the implementor: Avarda's checkout calls back into this store to create and
maintain a shipping session, Magento answers with its own carriers' rates, and
the option list is rendered inside the Avarda checkout. Registers as the
`partner` provider (an alternative to the nShift provider).

## Requirements

- `avarda/shipping-broker`
- `avarda/checkout3`

## Configuration

Under **Stores → Configuration → Sales → Payment Methods → Avarda Checkout V3 →
Avarda Shipping Broker**, set **Provider** to `partner`. This is the
shipping-provider switch (shared with nShift); choosing `partner` activates this
module and reveals its settings:

| Field | Notes |
|---|---|
| Partner Bearer Secret | Must match the secret configured in the Avarda merchant portal. Use the **Generate** button to create a random one. |
| Session TTL (seconds) | Default `3600` |

Also set the implementor base URL in the Avarda merchant portal to the store
host (`https://<host>`), using the same bearer secret on both sides.

## Shipping methods

All active Magento shipping methods are offered as plain delivery options — core
carriers (flat rate, free shipping, table rate) and custom carriers alike, with
no extra modules and no per-method configuration. Only pickup-point carriers
need an add-on provider.

## Pickup points

Carriers that deliver to pickup points (parcel lockers, service points) are
supported through add-on bridge modules, one per carrier. A bridge lists the
points near the delivery address and stores the chosen point so the carrier's
label printing keeps working. This module ships no pickup-point providers
itself; plain delivery works without any. If a carrier's pickup point lookup
fails, the option falls back to plain delivery.

## Order data

When the order is placed, the broker's placeholder shipping method is replaced
with the Magento method the customer actually chose, and a snapshot of the
selection is stored on the order's shipping address as
`avarda_shipping_selection` (JSON), exposed as an order address extension
attribute. Sessions live in their own table, keyed by the Avarda purchase ID.

## Notes and limitations

- Only the module's own shipping session records are written; the quote is never
  modified by the partner endpoints.
- Session expiry is stored but not yet enforced, and there is no cleanup job for
  old session records.
- The widget's selection request is fire-and-forget; a failure is logged in the
  browser console but not retried.
