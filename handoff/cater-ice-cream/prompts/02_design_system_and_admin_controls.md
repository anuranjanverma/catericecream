# Prompt 02 Design system and admin controls

Implement the global Design System service and admin screen from sections 4 and 5. One saved token configuration must drive frontend CSS variables and editor presets. Keep theme.json defaults, validate settings and avoid a second unrestricted source of global style overrides. Implement base colours, action/hover/focus roles, font families and weights, responsive heading/body scales, spacing, content width, radius and component settings.

Add named contexts for brand, Birthday, Wedding, Anniversary, Corporate and treat moods. Implement sparse page, section and element overrides with visible inheritance and reset. Do not freeze inherited values by copying the complete palette into every page. Bound numeric inputs, validate hex values, allow only approved font families/assets and emit safe scoped CSS.

Create a style preview showing real buttons, fields, headings, cards, accordions and a call panel. Verify changing one global action colour or body font updates all inheriting examples, while an explicit local override stays local. Reset it and verify inheritance returns. Verify mobile and desktop size changes and persistence after reload/cache clear. Check contrast warnings. Do not hardcode per-page CSS colours outside the central defaults/preset configuration. Supply screenshots and test evidence.

## Version 1.1 acceptance additions

Global heading/body font and colour changes must propagate across all occasion presets. Seed central accent tokens, not frozen per-page values. Add native admin controls for all three header rows, mobile navigation and footer columns; use no ACF Pro APIs.
