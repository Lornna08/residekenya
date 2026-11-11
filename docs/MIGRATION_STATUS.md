# Reside Ke migration status

## Direction
- Rebuild the PHP application as a Next.js App Router application for Vercel.
- Preserve the supplied database records and relationships.
- Retain the existing property media.

## Database export audit
The supplied MariaDB 10.11.19 dump contains 19 tables and 915 inserted rows. It includes 37 properties, 419 property_images rows, 2 users, 3 agents, 4 inquiries, 89 activity-log entries, 3 testimonials, 20 settings, 198 property-amenity links, 73 towns, 10 counties, 6 estates, 35 categories and 15 amenities.

The dump contains zero rows in bookings, open_houses, submissions and subscribers. The schema exists, but this export has no records in those tables.

## Remaining work
- Choose a managed MySQL-compatible database for the Vercel application.
- Implement database mappings and authentication for all 19 tables.
- Import into staging and verify row counts and foreign keys.
- Configure persistent media storage for uploads.
- Reconcile property images and test all public and admin workflows.

## Security
The raw SQL export is intentionally excluded from Git because it contains personal/contact data, account records, IP/activity logs and password hashes. Live config files and credentials are also excluded. Rotate database credentials and reset admin passwords during cutover.

This repository is a starter migration workspace, not production-ready software.
