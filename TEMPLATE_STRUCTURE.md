# Template Structure

## Overview

The templates have been refactored to eliminate duplication and create a single source of truth for common layout elements.

## Structure

```
templates/
├── layout/
│   └── base.html.twig          # Main base layout (HEAD, header, footer, avatar box)
├── app/
│   └── base.html.twig          # App-specific layout (extends layout/base.html.twig)
├── club/
│   └── base.html.twig          # Club-specific layout (extends layout/base.html.twig)
└── public/
    └── base.html.twig          # Public layout (extends layout/base.html.twig)
```

## How It Works

### 1. Main Base Layout (`layout/base.html.twig`)

Contains all common elements:
- HTML structure (DOCTYPE, head, body)
- Header with navbar
- Avatar box and user dropdown
- Flash messages
- Main content area
- JavaScript includes

**Blocks available for customization:**
- `title` - Page title
- `stylesheets` - Additional CSS
- `importmap` - Asset mapper
- `navbar_brand` - Logo/brand link
- `user_dropdown` - Entire user dropdown area
- `user_dropdown_subtitle` - Subtitle under username
- `user_dropdown_menu` - Menu items in dropdown
- `body` - Main content
- `javascripts` - Additional JS

### 2. App-Specific Layouts

Each app extends the main base and customizes only what's different:

#### `app/base.html.twig`
- Sets title to "Administration"
- Logo links to `app_dashboard`
- User dropdown: Profile + Logout

#### `club/base.html.twig`
- Sets title to club name
- Logo links to `club_dashboard`
- User dropdown subtitle shows club name
- User dropdown: Profile + Back to App + Admin + Logout

#### `public/base.html.twig`
- No header (public pages without authentication)
- Simple layout for login, registration, etc.

## Benefits

1. **Single Source of Truth**: Changes to avatar box, header, or footer only need to be made in `layout/base.html.twig`

2. **Easy Customization**: Each app can easily customize its menu by overriding specific blocks

3. **Maintainability**: No more duplication across multiple template files

4. **Consistency**: All apps share the same base structure and styling

## Example: Adding a New Menu Item

To add a menu item to the app layout:

```twig
{# In templates/app/base.html.twig #}
{% block user_dropdown_menu %}
    <a href="{{ path('app_profile_edit') }}" class="dropdown-item">
        <i class="ti ti-user"></i>
        Mon profil
    </a>
    <a href="{{ path('app_settings') }}" class="dropdown-item">
        <i class="ti ti-settings"></i>
        Paramètres
    </a>
    <div class="dropdown-divider"></div>
    <a href="{{ path('public_logout') }}" class="dropdown-item">
        <i class="ti ti-logout"></i>
        Déconnexion
    </a>
{% endblock %}
```

## Example: Changing Avatar Box Style

To change the avatar box globally:

```twig
{# In templates/layout/base.html.twig #}
{% block user_dropdown %}
    <div class="nav-item dropdown">
        <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown">
            <span class="avatar avatar-sm bg-primary">{{ app.user.firstname[:1] }}{{ app.user.lastname[:1] }}</span>
            {# ... rest of the code ... #}
        </a>
    </div>
{% endblock %}
```

This change will automatically apply to all apps (app, club, etc.).

