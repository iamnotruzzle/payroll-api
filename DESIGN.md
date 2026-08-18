---
name: MMMHMC Authentication Entry Surface
description: A task-first institutional gateway for secure employee access to HRIS and payroll.
colors:
  institutional-blue: "#235d90"
  institutional-blue-deep: "#194b77"
  institutional-blue-night: "#76a9d4"
  gateway-mist: "#f3f6f8"
  gateway-night: "#09121c"
  paper: "#ffffff"
  night-panel: "#101f2d"
  ink: "#172433"
  ink-night: "#e8f0f7"
  slate: "#52677b"
  slate-night: "#a8b9c9"
  field: "#fbfcfd"
  field-night: "#0c1925"
  field-line: "#bdcbd6"
  field-line-night: "#40576b"
  danger: "#b73a4b"
  danger-surface: "#fff1f2"
  danger-night: "#ffb6bf"
  danger-night-surface: "#35161d"
typography:
  display:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(3rem, 5.4vw, 5.25rem)"
    fontWeight: 780
    lineHeight: 0.98
    letterSpacing: "-0.04em"
  headline:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.85rem"
    fontWeight: 770
    letterSpacing: "-0.025em"
  body:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.7
  body-compact:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.88rem"
    fontWeight: 400
  label:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 720
  detail:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.71rem"
    fontWeight: 400
    lineHeight: 1.55
  action:
    fontFamily: "Manrope Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.82rem"
    fontWeight: 760
rounded:
  control: "0.6rem"
  control-relaxed: "0.65rem"
  message: "0.7rem"
  card-mobile: "0.85rem"
  card: "1rem"
spacing:
  field-gap: "0.48rem"
  compact: "0.65rem"
  base: "1rem"
  form-gap: "1.2rem"
  section-gap: "1.5rem"
  card-mobile: "1.4rem"
  card-min: "1.75rem"
  card-max: "2.5rem"
components:
  button-primary:
    backgroundColor: "{colors.institutional-blue}"
    textColor: "{colors.paper}"
    typography: "{typography.action}"
    rounded: "{rounded.control}"
    padding: "0 1rem"
    height: "3.15rem"
  button-primary-hover:
    backgroundColor: "{colors.institutional-blue-deep}"
    textColor: "{colors.paper}"
    typography: "{typography.action}"
    rounded: "{rounded.control}"
    padding: "0 1rem"
    height: "3.15rem"
  text-field:
    backgroundColor: "{colors.field}"
    textColor: "{colors.ink}"
    typography: "{typography.body-compact}"
    rounded: "{rounded.control}"
    padding: "0 0.85rem"
    height: "3rem"
  signin-card:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "clamp(1.75rem, 3vw, 2.5rem)"
---

# Design System: MMMHMC Authentication Entry Surface

## Overview

**Creative North Star: "The Trusted Threshold"**

The authentication entry surface is a calm, institutional threshold into sensitive employee systems. It feels dependable rather than promotional: direct language, a cool neutral field, one restrained blue accent, and a sign-in card that is unmistakably the primary task.

This record applies to login and closely related authentication entry surfaces only. It does not establish visual rules for payroll processing, HR administration, reporting, tables, or other authenticated product screens. Within this boundary, employee self-service and HR/Accounting role context remain useful orientation, never competition for the sign-in action.

**Key Characteristics:**

- Task-first hierarchy with sign-in as the dominant action.
- Restrained institutional blue against cool, quiet neutrals.
- Manrope Variable throughout, with firm weight contrast and compact support text.
- A connected workforce constellation that explains People, Time & Leave, and Payroll without competing with sign-in.
- Light and dark themes that preserve the same hierarchy and interaction states.
- Rounded controls and cards on a disciplined 10–16px surface scale.
- Motion confined to the sign-in surface and removed under reduced-motion preferences.

## Colors

The palette is cool, legible, and deliberately narrow: blue signals action and focus while neutral values carry nearly everything else.

### Primary

- **Institutional Blue:** The sole light-theme accent for the primary action, focused fields, selected controls, and the emphasized word in the entry headline.
- **Institutional Blue Deep:** The primary button's hover response; use it as a state, not a second accent.
- **Institutional Blue Night:** The dark-theme counterpart for accent text, focus borders, and active control details.

### Neutral

- **Gateway Mist / Gateway Night:** Paired page backgrounds that frame the authentication task without visual noise.
- **Paper / Night Panel:** Paired sign-in surfaces; these are the strongest tonal separation on the page.
- **Ink / Ink Night:** Paired high-emphasis text colors for titles and critical labels.
- **Slate / Slate Night:** Paired secondary text colors for instructions, role context, and help copy.
- **Field / Field Night:** Paired input fills that remain distinct from their containing sign-in surface.
- **Field Line / Field Line Night:** Paired field borders used at rest; interaction shifts them to the active theme accent.
- **Danger / Danger Surface / Danger Night:** Error-only colors for invalid fields and the sign-in alert. They do not expand the general accent palette.

### Named Rules

**The One Accent Rule.** Blue alone carries action, focus, and selective emphasis; do not introduce a second decorative accent on authentication entry surfaces.

**The Parity Rule.** Dark mode preserves semantic roles and contrast rather than mechanically inverting light-theme values.

## Typography

**Display Font:** Manrope Variable (with ui-sans-serif and system sans-serif fallbacks)  
**Body Font:** Manrope Variable (with ui-sans-serif and system sans-serif fallbacks)

**Character:** A single variable sans-serif keeps the gateway contemporary, readable, and operational. Authority comes from controlled weight, short measures, and restrained negative tracking rather than a decorative display face.

### Hierarchy

- **Display** (780, fluid 3rem–5.25rem, 0.98 line-height): The two-line entry statement only; keep it within 13 characters per line when composition allows.
- **Headline** (770, 1.85rem): The sign-in card title and equivalent authentication task headings.
- **Body** (400, 1rem, 1.7 line-height): Primary explanatory copy, capped at the observed 34rem measure.
- **Compact Body** (400, 0.88rem): Form entry text and placeholders.
- **Label** (720, 0.75rem): Field labels and compact operational headings.
- **Detail** (400, 0.71rem, 1.55 line-height): Field help, access guidance, and assistance copy.
- **Action** (760, 0.82rem): Primary submit labels.

### Named Rules

**The Weight-Not-Decoration Rule.** Build hierarchy with Manrope's variable weights and measured scale; do not add ornamental typefaces, all-caps slogans, or exaggerated letter spacing to this surface.

## Layout

The desktop gateway uses a centered container capped at 82rem with 1.5rem side gutters. Its main area is a two-column grid: flexible orientation content on the left and a constrained sign-in column between 22rem and 27rem on the right. The gap flexes from 4rem to 9rem, keeping the task visibly separate without isolating it from context.

At 900px and below, the layout becomes one column and deliberately reorders content: entry statement first, sign-in second, role context third, and the security note last. The sign-in card caps at 32rem. At 600px and below, side gutters reduce to 1rem, the card padding becomes 1.4rem, the card radius relaxes to 0.85rem, and role summaries stack vertically.

Above 900px, the left column includes a bounded 38rem workforce constellation below the orientation copy. Its three nodes map People, Time & Leave, and Payroll back to one secure workspace. The constellation is explanatory atmosphere, not navigation: it is hidden from assistive technology, replaced by equivalent role context in the document, and removed visually when the layout stacks.

Spacing inside the form is compact and regular: 0.48rem within a field, 1.2rem between form items, and 1.75rem before the form. Support and help content remain close enough to the task to be found without challenging it.

**The Task-Before-Context Rule.** On narrow screens, sign-in must appear before role details and security context.

## Elevation & Depth

The system is flat by default. Hairline blue-tinted dividers and quiet tonal fields organize the page; the sign-in card alone receives substantial ambient elevation. In light mode its shadow is `0 1.8rem 4.5rem -3.1rem rgba(28, 71, 108, .62)`; in dark mode it becomes `0 1.8rem 4.5rem -3rem rgba(0, 0, 0, .72)`. The primary button carries a smaller action shadow that strengthens on hover.

### Named Rules

**The Single Lift Rule.** The sign-in surface is the only elevated container; role summaries, security notes, header, and footer remain flat.

## Shapes

Authentication surfaces use gently rounded rectangles with no pill-shaped primary controls. Inputs and the submit button share a compact 0.6rem radius. Small utility controls may use approximately 0.65rem, alerts 0.7rem, and the sign-in card scales from 0.85rem on phones to 1rem on larger screens. Borders are thin and functional; rounding does not substitute for hierarchy.

**The 10–16 Rule.** Keep the main control and surface radius vocabulary between roughly 10px and 16px; tiny checkbox and toggle internals are proportional exceptions, not new container shapes.

## Authenticated Launcher Extension

The `/home` surface extends the authentication entry system into a permission-aware application launcher. This boundary applies only to launcher mode; authenticated module pages, sidebars, tables, and workflows retain their existing product-shell rules.

**Creative North Star: “The Clinical Command Index.”** The launcher should feel like a precise institutional control surface rather than a dashboard or consumer app grid. One compact workspace frame establishes place, role-based groups make scanning predictable, and technical launch rows give each permitted module enough context to choose confidently.

- **Grouping:** My Workspace contains employee self-service; Workforce Operations contains people, time, leave, development, and payroll tools; Administration contains configuration and access tools. Empty groups are removed after permission filtering.
- **Hierarchy:** Self Service spans the full desktop row as the employee anchor. Operational applications use equal compact launch rows so the complete permission set remains visible without scrolling on standard desktop viewports.
- **Palette:** Institutional Blue and cool blue-neutral tonal variants are the only launcher accents. Module identity comes from typography, position, and subtle tonal variation—not a rainbow icon palette.
- **Tile behavior:** Module name is primary, icon is secondary, and a one-line description sits under the name so every tile has a readable caption. Long copy truncates with an ellipsis. Taller stacked cards on touch and narrow layouts wrap the full description. The trailing arrow sits optically centered in its square.
- **Availability:** Unfinished modules include a written “Coming soon” state plus a neutral status dot; availability never relies on color alone.
- **Atmosphere:** A quiet cool surface replaces the old grid field. Two geometric orbital points revolve over 60–72 seconds, with all automatic motion paused in hidden tabs and disabled for reduced-motion preferences.
- **Technical detailing:** Sparse axes, calibration marks, connection nodes, corner brackets, and signal rails create clinical-command precision without introducing fake telemetry, decorative grid fields, or a second accent color.
- **Interaction material:** Tiles remain neutral at rest. Hover and keyboard focus introduce a module-toned wash, one short signal sweep, stronger corner registration, and a restrained two-pixel lift; pressed state settles the surface back into place.
- **Responsive grid:** Four compact columns on wide desktops, two columns at tablet widths, and one column on phones. At 900px and above the launcher fits the working viewport; narrower layouts return to natural document scrolling so text and touch targets remain comfortable.
- **Shape and interaction:** Launcher tiles use the established 1rem surface radius. Hover, focus, and press feedback stays within 220–300ms, with a clearly offset keyboard focus ring.

## Authenticated Application Shell

The default authenticated shell extends the same institutional language into task-heavy screens. It is an **Operate** surface: navigation clarity, density, and stable geometry take priority over the more expressive atmosphere used at login and on the launcher.

**Creative North Star: “The Working Index.”** The shell should feel like the persistent operating layer beneath the Clinical Command Index: quiet enough for long sessions, exact enough for HR and payroll work, and visibly part of the same product.

- **Shell palette:** Institutional Blue (`#235d90`) is the only interactive accent in light mode; Institutional Blue Night (`#76a9d4`) carries the same role in dark mode. Cool paper, ink, slate, and field tones map directly from the entry system.
- **Page frame:** The content field is a quiet tonal surface without a decorative grid. Low-contrast ambient blue may collect near the top edge, but it must not compete with forms, tables, or alerts.
- **Sidebar:** The sidebar is a stable cool-paper rail with a fine blue-neutral divider. Brand, All Apps, current module, and section navigation follow a clear top-to-bottom hierarchy. Active links use a pale blue field, blue text, and a one-pixel registration mark rather than a saturated fill.
- **Top bar:** The top bar is a compact continuation of the page surface with a fine divider and restrained depth. Module context leads; All Apps, theme, and account controls remain secondary. Controls share the 0.65–0.7rem utility radius and a visible offset focus ring.
- **Account menu:** The trigger and menu use the same paper/ink/slate roles as the shell. The avatar is a small blue-tinted identifier, not a second brand mark. Destructive logout styling remains semantic red.
- **Responsive behavior:** Desktop keeps a 248px persistent navigation rail when open. Below the desktop breakpoint the rail becomes a bounded navigation region above content; controls remain at least 36px high, labels truncate safely, and opening or closing the rail never changes route state.
- **Motion:** Shell chrome uses only short 140–220ms state transitions. It does not inherit the portal or launcher entrance choreography or ambient orbital motion.
- **Accessibility:** Keyboard focus uses a clearly offset blue ring; current navigation is conveyed by text, tone, and registration mark; touch targets remain usable; light and dark themes preserve semantic contrast.

### Shared Overlays

- **Structure:** Modals and drawers remain present in the first response and open entirely in Alpine. Livewire remains responsible only for save, delete, and real data loads.
- **Surface:** Panels use Paper / Night Panel, a fine blue-neutral edge, a 0.85rem modal radius, and restrained offset depth. Drawers keep a square viewport edge and a single divider against the backdrop.
- **Header:** A compact technical header uses a short blue registration line, clear title, optional description, and a labeled Close control. Body content scrolls independently.
- **Backdrop:** Use a dark blue-black veil with modest blur only to preserve focus separation; it must not become decorative glass.

## Dedicated Payroll Workspace

Payroll generation retains its chrome-free, viewport-owned layout because step navigation, unsaved-change protection, and wide payroll tables require a specialized operating frame.

- **Continuity:** Apply the shared Manrope typography, institutional palette, page field, focus rings, utility controls, and surface treatment without adding the standard application sidebar or top bar.
- **Header:** The payroll title and scope lead. Exit, theme, and configuration controls use the shared utility-control language and remain visible without dominating the workflow.
- **Step rail:** The fixed 300px desktop rail is the workspace navigation. Active steps use the same pale-blue field and registration mark as active app navigation, while accessible/read-only badges retain explicit text.
- **Responsive behavior:** The rail returns to document flow on narrower screens; the main payroll table keeps its existing independent overflow and sticky-column behavior.
- **Safety:** Unsaved-change confirmation, draft status, loading states, and configuration routes are behaviorally unchanged. Visual unification must never weaken these states or hide their labels.

## Components

### Buttons

- **Shape:** A compact rounded rectangle (0.6rem) with a minimum height of 3.15rem.
- **Primary:** Institutional Blue with white text, centered label, full available width, and 0 1rem padding.
- **Hover / Focus:** Hover deepens the blue, lifts by 1px, and strengthens the shadow; active presses down by 1px. Keyboard focus uses a visible 3px blue-tinted outline with 3px offset.
- **Disabled / Busy:** Reduce opacity, remove transform, and use a wait cursor while preserving the button's footprint.

### Cards / Containers

- **Corner Style:** 1rem on larger viewports and 0.85rem on phones.
- **Background:** Paper in light mode and Night Panel in dark mode.
- **Shadow Strategy:** The sign-in card is the sole lifted container; see Elevation & Depth.
- **Border:** None; tonal contrast and the ambient shadow define its edge.
- **Internal Padding:** Fluid 1.75rem–2.5rem, reduced to 1.4rem on phones.

### Inputs / Fields

- **Style:** A 3rem minimum-height field with a 1px neutral border, quiet tonal fill, 0.6rem radius, and 0 0.85rem padding.
- **Focus:** Shift the border to the theme accent, slightly brighten the fill, and add a 0.2rem translucent blue ring.
- **Error:** Shift the border to Danger and pair form-level failure with the dedicated error alert.
- **Password Action:** Keep Show/Hide inside the trailing 3.75rem of the field, underlined and keyboard-focusable.

### Selection Controls

- **Remember Checkbox:** A compact square with a neutral stroke at rest, blue fill and white check when selected, and a visible offset focus ring.
- **Theme Toggle:** A labeled utility control with a bordered neutral shell and a restrained sliding blue indicator; it remains secondary to sign-in.

### Authentication Alert

- **Style:** A compact stacked message with a 0.7rem radius, pale danger fill, matching border, strong summary, and supporting error text.
- **Behavior:** Receive focus after server validation failure so keyboard and assistive-technology users reach the error immediately.

### Role Context

- **Style:** Two flat text summaries separated from the main copy by thin blue-tinted top rules. They are orientation, not action cards, and do not receive buttons, shadows, or motion.

### Workforce Constellation

- **Style:** One bounded, low-contrast field containing three flat labeled nodes, two concentric paths, and restrained connection lines in the existing blue palette.
- **Meaning:** People covers records and self-service; Time & Leave covers schedules, DTR, and requests; Payroll covers processing and payslips. All three resolve to one secure workspace.
- **Behavior:** Decorative on desktop and hidden at 900px and below, where the semantic role summaries provide equivalent context after sign-in.
- **Depth:** Nodes use inset hairlines rather than drop shadows so the sign-in card remains the only lifted surface.

### Motion

- **Staged Entrance:** GSAP progressively reveals the headline, constellation, and sign-in surface with a 14px rise, 1.05-second duration, and 160ms stagger using `power2.out`.
- **Ambient System Motion:** Concentric paths revolve continuously in opposite directions over 44-56 seconds while connection pulses breathe over 2.8 seconds. The movement pauses when the page is hidden.
- **Interaction Feedback:** Controls use smooth 220–280ms transitions for focus, hover, and the theme indicator.
- **Reduced Motion:** Do not initialize GSAP and remove relevant control transitions when `prefers-reduced-motion: reduce` is active. Content must remain visible without JavaScript.

## Do's and Don'ts

### Do:

- **Do** make sign-in the strongest action and the only elevated container.
- **Do** preserve Employee ID, password, remember-me, validation, help, and theme behaviors when reusing the surface.
- **Do** maintain explicit light/dark token pairs and visible keyboard focus.
- **Do** place sign-in before role details at 900px and below.
- **Do** use the existing MMMHMC brand mark assets and Manrope Variable typography.
- **Do** keep the workforce constellation explanatory, non-interactive, and subordinate to sign-in.

### Don't:

- **Don't** turn authentication entry into a marketing landing page or give role summaries competing calls to action.
- **Don't** use multicolor decorative gradients, extra accent hues, or decorative cards; background gradients must remain low-contrast atmosphere rather than competing emphasis.
- **Don't** use pill-shaped fields or buttons; keep the main 10–16px radius vocabulary.
- **Don't** promote these entry-surface rules into unrelated payroll or administration screens without separate evidence.
- **Don't** hide access errors in transient toasts or color-only states.
- **Don't** show the constellation on narrow screens or turn its nodes into competing navigation cards.
