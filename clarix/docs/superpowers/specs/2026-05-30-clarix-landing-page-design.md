# Clarix Marketing Landing Page — Design Spec
**Date:** 2026-05-30  
**File:** `resources/views/welcome.blade.php` (in-place rewrite)  
**Route:** `/` (no auth required, existing auth redirect preserved)

---

## Goal
Replace the existing landing page with a stunning, production-grade SaaS marketing page at Linear/Notion quality. Target audience: content/creative teams considering adopting Clarix.

---

## Design System

| Token | Value |
|---|---|
| Background gradient | `#ede9fe → #f5f3ff` (light lavender) |
| Accent / primary | `#7c3aed` (violet-600) |
| Button gradient | `linear-gradient(135deg, #7c3aed, #6d28d9)` |
| Footer bg | `#1e1b4b` (dark indigo) |
| Glassmorphism card | `rgba(255,255,255,0.2)`, `backdrop-blur`, `1px rgba(255,255,255,0.3)` border, soft drop shadow |
| Typography | Inter (loaded via bunny.net), bold headings, light body |
| Animations | CSS keyframes for load animations + Intersection Observer scroll-reveal |

---

## Sections (top to bottom)

### 1. Navbar (sticky)
- White bg + subtle bottom border + blur on scroll
- **Logo:** Purple `C` icon + "Clarix" wordmark (left)
- **Center links:** Features · Use Cases · Pricing · Testimonials
- **Right:** "Get started free →" purple filled button
- Mobile: hamburger → slide-down menu

### 2. Hero
- Background: lavender gradient + dot-grid overlay + decorative blur blobs
- **Pill badge:** `⚡ Project Management, Reimagined`
- **Headline:** "The smarter way to manage projects, teams and payments"
- **Subheading:** "Clarix brings your tasks, team roles, credit tracking and financial reporting into one clean portal. No more spreadsheets. No more chaos."
- **CTAs:** "Get started free →" (purple filled) + "See how it works" (ghost border)
- **Small text:** "No credit card required · Free to get started"
- **Two floating glassmorphism mock cards** (animated float):
  - Left (rotated -4°): Credits card — "Rs 12,450" · "+24% this month" · mini bar chart
  - Right (rotated +4°): Active Tasks — 4 tasks with Done/Active/Pending badges · 67% sprint progress bar
- Wavy SVG divider at bottom → white

### 3. Why Clarix
- White background
- Label: `✦ One Stop Solution`
- Heading: "Built for how teams actually work"
- **3 glassmorphism cards with Unsplash image backgrounds (CDN URLs)**:
  1. People collaborating → "Effortless Collaboration" — task assignment, writers, PMs
  2. Analytics dashboard → "Real-time Credit Tracking" — auto credit logging
  3. Team meeting → "Role-Based Access" — tiered visibility

### 4. Features Grid
- Light lavender background
- Label: `✦ Everything Covered`
- Heading: "Some more Clarix features"
- **6 cards (3×2 grid)**, white glassmorphism, purple icons:
  1. Smart Task Assignment
  2. Credit & Payment System
  3. Financial Dashboard
  4. File Management
  5. Issue Reporting
  6. Multi-Role Portal

### 5. Pricing
- White background
- Label: "Pay as you grow"
- Heading: "Simple, transparent pricing"
- **4 tiers** (Free · Standard · Premium · Enterprise), Standard highlighted in purple:
  - Free: Rs 0/month — 3 projects, 5 members, basic task mgmt, email support
  - Standard ⭐: Rs 4,500/month — unlimited projects, 25 members, credit tracking, financial dashboard, 500MB uploads, priority support
  - Premium: Rs 8,000/month — everything in Standard + unlimited members, advanced analytics, custom roles, 5GB storage, dedicated support
  - Enterprise: Custom — everything in Premium + custom integrations, SLA, on-premise, account manager

### 6. Testimonials
- **Lavender background** (same as hero gradient)
- Heading: "What our users say"
- **3 glassmorphism cards**, each with: 5 stars, quote, avatar initials circle, name, role
  - Business owner perspective
  - Project manager perspective
  - Team lead perspective

### 7. Footer
- Background: `#1e1b4b`
- Left: Clarix logo + tagline ("The smarter way to manage projects, teams and payments")
- **3 link columns:**
  - Product: Features, Pricing, Changelog
  - Company: About, Blog, Careers
  - Support: Help Center, Contact, Privacy Policy
- Bottom bar: "© 2026 Clarix. All rights reserved."

---

## Implementation Notes

- All sections use scroll-reveal via `IntersectionObserver` (`.reveal` → `.reveal.in`)
- Unsplash CDN: `https://images.unsplash.com/photo-{ID}?w=800&q=80&auto=format&fit=crop`
- Mobile-first responsive; all grids collapse to 1-column on mobile
- Pricing cards use CSS grid, Standard card is slightly elevated (`scale(1.03)`) with purple gradient bg
- No Laravel/Livewire components needed — pure Blade + inline Tailwind + `<style>` block
- File is standalone (does not extend `layouts/app.blade.php`)
