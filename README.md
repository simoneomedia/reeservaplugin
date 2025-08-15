
# Reeserva Booking Suite

WordPress booking plugin with:
- Single **Accommodation** post type
- Calendar price editor with **periods & per-period variations**
- **Multi-step checkout** with optional **Stripe payments**
- **iCal** import (Airbnb/Booking.com) and export feed
- Email notifications
- Frontend admin shortcodes
- GitHub self-updater (no extra plugins)

## Auto-updates
Create a GitHub **Release** tag (e.g. `v1.2.1`) — WordPress will offer an update.

## Stripe
Set keys under **Reeserva → Email & Payments → Stripe**. If disabled, bookings confirm without payment.

## iCal
- Add external ICS URLs on the Accommodation edit screen.
- Export feed: `/?rsv_ics=ID&key=YOUR_SITE_KEY` (key shown on the edit screen).

