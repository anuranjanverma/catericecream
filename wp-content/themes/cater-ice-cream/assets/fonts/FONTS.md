# Bundled fonts

Self-hosted WOFF2 files. Nothing is loaded from Google or another CDN at runtime.
Only the families, styles and weights used by the approved designs are bundled, latin subset only
(U+0000-00FF plus common punctuation; covers Spanish place names such as "Cañada").

| File | Family | Style | Weights covered | Used for |
| --- | --- | --- | --- | --- |
| `fraunces/fraunces-roman-latin.woff2` | Fraunces (variable, wght) | normal | 600–700 | Headings (700 base, 600 Wedding) |
| `fraunces/fraunces-italic-latin.woff2` | Fraunces (variable, wght) | italic | 600 | Wedding italic heading emphasis |
| `dm-sans/dmsans-roman-latin.woff2` | DM Sans (variable, wght) | normal | 400–700 | Body 400, interface 500, strong 700 |

## Source

Downloaded 2026-10-19 from the official Google Fonts distribution (the families' official publisher):

- CSS API request: `https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@400;500;700&display=swap`
- Fraunces files: `fonts.gstatic.com/s/fraunces/v38/…` (latin subset entries)
- DM Sans file: `fonts.gstatic.com/s/dmsans/v17/…` (latin subset entry)
- Upstream projects: https://github.com/undercasetype/Fraunces and https://github.com/googlefonts/dm-fonts

SHA-256:

```
7f9d191d999336d3b9790afa72e1358e50a13b06d4f289341e92a311967a80f9  fraunces/fraunces-roman-latin.woff2
23712db611d0799df860504f0e6626ade20588fab2b8f4882d0e711a330da1b7  fraunces/fraunces-italic-latin.woff2
9fea608a947e67020c33cad9a6fe3d60c54119dfb8cff87768a8117a15ed7543  dm-sans/dmsans-roman-latin.woff2
```

## Licence

Both families are licensed under the SIL Open Font License 1.1. The licence text, copied from
`https://github.com/google/fonts/tree/main/ofl/{fraunces,dmsans}/OFL.txt`, is kept beside each font
(`fraunces/OFL.txt`, `dm-sans/OFL.txt`). OFL permits bundling and self-hosting with the website;
the fonts must not be sold on their own.

## Rules

- Font families are declared once in `theme.json` (`settings.typography.fontFamilies`), which makes WordPress
  print the `@font-face` rules on the frontend and in the editor.
- The Design System screen only offers the families declared there (plus system fallbacks). There is no font upload path.
- The WordPress Font Library is disabled in `theme.json` so it cannot become a second source of fonts.
