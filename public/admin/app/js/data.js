/* ============================================================
   Reside Kenya — constants (stages, statuses, lists). Live data comes
   from the server: see store.js and /admin/api.php.
   ============================================================ */
window.ES = window.ES || {};

(function () {
  'use strict';
  var DAY = 864e5;

  var BOOT = window.RESIDE_BOOT || {};
  ES.config = {
    product: 'Reside Kenya',
    agency: 'Reside Kenya',
    city: 'Nairobi',
    currency: 'KES',
    locale: 'en-KE',
    userId: BOOT.me || 'u1',
    saleRate: 3,          // % of sale price (editable in Settings)
    rentMonths: 1,        // letting fee, in months of rent
    monthlyTarget: 1000000,
    site: BOOT.site || ''
  };

  ES.STAGES = [
    { id: 'new', name: 'New opportunity', short: 'New', p: 10, c: '#94A3B8' },
    { id: 'qualified', name: 'Qualified', short: 'Qualified', p: 20, c: '#7C8CF8' },
    { id: 'viewing', name: 'Viewing scheduled', short: 'Viewing', p: 35, c: '#38BDF8' },
    { id: 'offer', name: 'Offer submitted', short: 'Offer', p: 50, c: '#F59E0B' },
    { id: 'under_offer', name: 'Under offer', short: 'Under offer', p: 70, c: '#2F5BEA' },
    { id: 'contract', name: 'Contract', short: 'Contract', p: 90, c: '#0F1729' },
    { id: 'won', name: 'Closed won', short: 'Closed won', p: 100, c: '#22A06B' },
    { id: 'lost', name: 'Closed lost', short: 'Lost', p: 0, c: '#E0473C' }
  ];

  ES.LEAD_STATUS = [
    { id: 'new', name: 'New' }, { id: 'contacted', name: 'Contacted' }, { id: 'qualified', name: 'Qualified' },
    { id: 'viewing', name: 'Viewing scheduled' }, { id: 'negotiating', name: 'Negotiating' },
    { id: 'converted', name: 'Converted' }, { id: 'lost', name: 'Lost' }
  ];

  ES.PTYPES = ['Detached house', 'Villa', 'Apartment', 'Townhouse', 'Office', 'Retail'];
  ES.AREAS = ['Runda', 'Karura', 'Kitisuru', 'Lavington', 'Kileleshwa', 'Karen', 'Westlands', 'Kilimani', 'Parklands', 'Upper Hill', 'Riverside', 'Gigiri', 'Ruaka', 'Syokimau', 'Limuru', 'Tigoni', 'Nyali', 'Diani', 'Mombasa Road'];
  ES.AMENITIES = ['Swimming pool', 'Garden', 'Borehole', 'Backup generator', 'Solar water heating', 'DSQ (staff quarters)', 'Gym', 'Lift', 'CCTV & 24h security', 'Electric fence', 'Fibre internet', 'Balcony', 'Study', 'Walk-in closet', 'Rooftop terrace', 'Children\'s play area', 'Ample parking', 'Sea view'];
  ES.SOURCES = ['Website', 'Website booking', 'Website — list property', 'WhatsApp', 'Instagram', 'TikTok', 'Facebook', 'Phone call', 'Referral', 'Walk-in', 'Property portal', 'Signboard'];
  ES.DOC_KINDS = { listing: 'Listing agreement', offer: 'Offer letter', sale: 'Sale agreement', lease: 'Lease agreement', photos: 'Property photographs', inspection: 'Inspection report', disclosure: 'Disclosure document', valuation: 'Valuation report', title: 'Title deed / search', id: 'ID / KRA documents', other: 'Other' };

})();
