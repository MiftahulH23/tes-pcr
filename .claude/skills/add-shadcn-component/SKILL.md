---
name: add-shadcn-component
description: Add a shadcn/ui primitive to resources/js/components/ui and adapt it to this project's conventions (semantic tokens, outline focus, dark mode, phone fit) without clobbering the customized ui files. Use when a new UI primitive such as tabs, popover, switch, or textarea is needed.
---

# add-shadcn-component

All UI is built from `resources/js/components/ui/*`. Before adding anything, check `ls resources/js/components/ui` — the primitive may already exist.

## Customized files — do not overwrite

`dialog.tsx`, `input.tsx`, `select.tsx`, `table.tsx` carry local changes (rounded corners and responsive width/padding on the dialog, `outline-*` focus instead of `ring-*`, and `containerRef` on the table). If the CLI asks to overwrite any of them, or a new component lists one as a dependency, answer **no**. Afterwards, `git diff resources/js/components/ui` must show no unintended change to them.

## Steps

1. **Add it.** `npx shadcn@latest add <name>` (config: `components.json` — default style, lucide icons, `@/components/ui` alias). `components.json` still names a `tailwind.config.js` that doesn't exist, because Tailwind v4 is configured in CSS (`resources/css/app.css`). If the CLI can't cope, write the component by hand from the shadcn source, matching the shape of sibling files. Confirm any new `@radix-ui/react-*` dependency landed in `package.json` and the lockfile.
2. **Focus style.** Replace `focus-visible:ring-*`, `focus:ring-*`, and `ring-offset-*` with `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring` (see `input.tsx`). Box-shadow rings left artifacts inside animated Radix dialogs.
3. **Colors.** Only semantic tokens exist (`background`, `foreground`, `card`, `popover`, `primary`, `secondary`, `muted`, `accent`, `destructive`, `border`, `input`, `ring`, plus chart and sidebar). shadcn output already uses them; remove any raw colors. Dark mode is the manual `.dark` class, so tokens flip on their own — never add a theme prop from the library.
4. **Overlays on phones.** Give popovers, sheets, and menus side gutters and rounded corners like `dialog.tsx` (`w-[calc(100%-2rem)]`), and confirm nothing is wider than a ~360px screen. `html, body` are `overflow-x: hidden`, so overflow gets clipped, not scrolled.
5. **Animations.** The `tailwindcss-animate` plugin is enabled (`animate-in`, `fade-in-0`, `zoom-in-95`, …).
6. **Formatting.** `resources/js/components/ui/*` is in `.prettierignore`: leave generated style (double quotes, no semicolons) alone. Code that *uses* the component is prettier-checked.
7. **Use it** from a feature component in `components/enrollments/`, with Indonesian copy and an `aria-label` for icon-only controls. Check light and dark mode and phone width.
8. Run the `verify` skill.

## Keep this list honest

If you customize another `ui/` file beyond generated output, add it to the "Customized files" list above so the next person doesn't overwrite it.
