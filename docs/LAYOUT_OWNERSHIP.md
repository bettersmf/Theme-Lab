# Theme Lab Layout Ownership

## RC6.1.19 baseline

Theme Lab follows one-owner-per-property for published layouts.

| Component | Primary selector | Width owner | Notes |
|---|---|---|---|
| Utility frame | `#top_section .inner_wrap` | Shared outer frame | `#upper_section` fills this frame and owns only its internal surface and lower radius. |
| Header | `body #header` | Shared outer frame | Header imagery and padding remain owned by Header Studio. |
| Forum body | `body #wrapper` | Shared outer frame | `#content_section` and `#main_content_section` fill it at 100%; they do not calculate another page width. |
| Footer | `#footer .inner_wrap` | Shared outer frame | Uses the same maximum width and side gutters. |
| Board rows | `#boardindex_table .windowbg` and `.boardindex_table .windowbg` | Board Index row | Owns icon/info/stats/last-post spacing; minimum inline padding is 18px. |
| Forum controls | `.button`, `.button_submit`, button-list/action links, and submit inputs | Buttons Studio | Bare `button` elements and reset/button inputs are intentionally not globally styled to protect SMF administration controls. |

## Rule

A nested component may fill its parent with `width: 100%`, but it must not repeat the outer frame's `calc(...)`, maximum width, or auto margins.


## RC6.1.20 Surface Continuity

- `#content_section` and `#main_content_section` no longer own horizontal padding.
- `#boardindex_table` and its category containers fill the shared frame.
- Board View topic lists own only the upper corners.
- The immediately following lower `.pagesection` owns the lower corners.
- Rows between those boundaries remain square so the page reads as one continuous surface.


## RC6.1.20b Board Row Insets

- **Outer row owner:** `themelab_compile_surface_translation_authority()`
- **Vertical breathing room:** final Board Index row `padding-block`
- **Horizontal breathing room:** final Board Index row `padding-inline`
- **Edge protection:** explicit start inset on `.board_icon` and end inset on `.board_stats` / `.lastpost`
- Earlier reconstruction passes may establish the grid, but this final authority owns visible row spacing.
