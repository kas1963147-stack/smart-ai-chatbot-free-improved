# UI Foundation and Standardization

This document defines the UI approach for the admin add-on so future modules
can scale without visual drift. The goal is to keep a consistent, premium
experience while staying safe for CodeCanyon distribution.

## Goals
- One visual language across every module and feature.
- A small, reusable component layer that replaces ad-hoc markup and inline styles.
- Predictable CSS naming and composition so new screens are fast to build.
- Avoid licensing risk by not copying third-party UI kits.

## Architecture
1. Design tokens (single source of truth)
   - `assets/admin-react/styles/design-system-v2.css`
   - Colors, spacing, typography, radii, shadows, and utilities.
2. Base UI styles
   - `assets/admin-react/styles/components.css`
   - Buttons, cards, tabs, forms, badges, tables, etc.
3. UI primitives (React)
   - `assets/admin-react/components/ui`
   - Button, Card, Tabs, and future atoms.
4. Feature modules
   - `assets/admin-react/components/<module>`
   - Module-specific logic and composition only.
5. Page-level styles
   - `assets/admin-react/styles/pages/*`
   - Only page-specific layout and customizations.

## Component taxonomy
- Foundations: design tokens and CSS utilities.
- Primitives: UI atoms in `components/ui` (Button, Card, Tabs, etc).
- Composites: shared blocks in `components/shared` (Modal, EmptyState, etc).
- Modules: features under `components/<module>` and their CSS in `styles/pages`.

## Naming and structure rules
- Use the `swc-` prefix everywhere for CSS classes.
- Avoid inline styles except for dynamic values that cannot be expressed with
  existing utilities.
- Prefer `swc-` utility classes (`swc-mb-6`, `swc-p-6`, `swc-text-muted`, etc).
- Avoid duplicate patterns; create a UI primitive instead.

## WordPress components usage
- Avoid `@wordpress/components` in admin React surfaces; use the UI kernel
  (`assets/ui`) instead.
- WP packages are still allowed for Gutenberg/editor-only UI where required by
  WordPress conventions.

## Extending the system
1. Add or extend a token in `design-system-v2.css` if a new semantic value is
   needed.
2. Add a reusable class in `components.css` (avoid page-specific CSS).
3. Create or update a UI primitive in `components/ui`.
4. Use the primitive in module components and keep styles scoped to pages only
   when needed.

## Accessibility baseline
- Buttons and tabs must be keyboard reachable.
- Use `aria-selected` on tabs and avoid removing focus outlines.
- Keep text contrast at or above 4.5:1.

## Licensing and distribution
- Do not copy third-party UI kits or proprietary design assets.
- If external assets are required, only use permissive licenses (MIT, Apache-2.0)
  and document them.
- Prefer in-house styling and tokens for a clean CodeCanyon submission.
