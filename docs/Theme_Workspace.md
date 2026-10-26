# Theme Workspace

## Workspace Import and Round-Trip Editing

Theme Workspace is the user-facing lifecycle manager for Theme Lab designs. The historic database and PHP identifiers continue to use `project` internally for backward compatibility; the administrator-facing product language is now **Workspace**.

### Actions

- **New Workspace** clears only the active selection and opens a blank workspace form. Saved workspaces are never deleted by this action.
- **Open Workspace** selects an existing saved workspace.
- **Import Workspace** accepts native Theme Lab JSON, generated theme ZIPs containing `themelab-workspace.json`, and older Theme Lab generated ZIPs that contain `css/themelab-generated.css`.

### Round-trip manifest

Every theme published by Theme Lab 1.0.1 contains `themelab-workspace.json`. It stores the editable Theme Lab workspace state alongside the generated theme so the theme can be imported back into Theme Lab without reverse engineering.

### Legacy import

Recognized older Theme Lab-generated themes can be reconstructed from Theme Lab's own `--tl-*` CSS manifest. Core colors, layout, typography, buttons, forms, navigation, Board Index, Header, Footer, gradients, and SMF-logo settings are restored. Original uploaded source images cannot always be recovered from CSS alone and may need to be selected again.
