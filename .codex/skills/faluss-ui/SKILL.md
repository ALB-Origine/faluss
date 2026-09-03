---
name: faluss-ui
description: Design or refine Faluss interfaces and Elementor components with the canonical Faluss.me visual system. Use for visual frontend work in the Faluss ecosystem; do not apply to backend, authentication logic, migrations, or unrelated products.
---

# Faluss UI

Design Faluss as a mature, unmistakable consumer-tech brand: the clarity of a category leader, the precision of a premium product, and one deliberate visual gesture per screen. The interface is confident and contemporary, never cold, generic, or theatrically “sexy”. Provocation belongs to the product’s voice when relevant, not to visual clutter.

The product brief and existing approved screens always override this skill.

## Canonical foundation

Use these tokens as defaults. Keep them configurable in the host system.

- Canvas: `#FFFDF5`
- Surface: `#FFFFFF`
- Ink: `#080808`
- Muted text: `#6F6A63`
- Accent: `#FF3D16`
- Fine border: `rgba(8, 8, 8, 0.12)`
- Soft shadow: `0 12px 30px rgba(8, 8, 8, 0.06)`
- Typeface: Outfit, then a clean system sans-serif fallback

Use abundant whitespace, controlled line lengths, strong typographic hierarchy, and sentence case. Make one action visually primary. Use the accent with intent: primary actions, active states, and small moments of emphasis—not large decorative fields.

## Visual character

- Let one distinctive choice carry the screen: a bold typographic composition, asymmetrical composition, restrained structural line, or a carefully selected branded visual. Keep the rest quiet.
- Cards are white, tactile and disciplined: fine borders, light shadows, purposeful radius. Do not turn every content group into an identical card.
- Buttons may be pill-shaped. Their label is direct and describes the result of the action.
- Use motion only when it clarifies a user action or creates one restrained entrance moment. Respect reduced motion.
- Preserve clarity on mobile first; keyboard focus, form states, errors and empty states are part of the design.

Avoid generic dark-purple/neon aesthetics, glassmorphism, decorative gradients, heavy shadows, stock-SaaS bento repetition, gratuitous all-caps eyebrows, filler copy, and ornamental labels that carry no information.

## Elementor and WordPress components

When building a Faluss component for Elementor:

- Expose normal Elementor content, style and advanced controls for all meaningful visual decisions: text, typography, colors, spacing, borders, radius, shadows and interaction states.
- Start from the canonical tokens above, expressed as component-scoped CSS custom properties.
- Scope CSS to the component. Do not alter the Elementor kit, theme defaults, `:root`, or unrelated global selectors.
- A shortcode fallback must render the same component and inherit the same canonical defaults.
- Do not hard-code presentation choices that Elementor already allows the user to control.

## Build standard

Choose the structure that best serves the actual content instead of applying a template. Before implementation, decide the page’s hierarchy, primary action, and single visual signature internally; then build and review the result at desktop and mobile widths. Remove decoration that does not improve comprehension, confidence, or brand recognition.
