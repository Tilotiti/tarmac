# Subdomain Architecture Implementation Summary

## Overview
Successfully refactored the Tarmac application to support subdomain-based architecture with three distinct domains:
- **www.domain** - Public area (landing page, login, registration, password reset)
- **app.domain** - Admin area (user/club management for ROLE_ADMIN users)
- **{subdomain}.domain** - Club-specific areas (wildcard subdomains for individual clubs)

## What Was Implemented

### 1. Database & Entity Layer ✅

**New Entities:**
- `Club` - Represents a club with name, subdomain (unique), description, active status
- `UserClub` - Join entity with ManyToOne relationships to User and Club
  - Includes `isManager` and `isInspector` boolean flags
  - Tracks `joinedAt` and `createdAt` timestamps

**Updated Entities:**
- `User` - Added relationship to UserClub
  - Added helper methods: `getClubs()`, `hasAccessToClub()`, `isManagerOfClub()`, `isInspectorOfClub()`, `isAdmin()`
  - Simplified roles to only `ROLE_ADMIN` and `ROLE_USER` (removed ROLE_MANAGER and ROLE_INSPECTOR)

**Repositories:**
- `ClubRepository` - With `findBySubdomain()`, `findAllActive()`, `queryByFilters()`
- `UserClubRepository` - For managing user-club relationships

**Migration:**
- Created migration `Version20251016083524.php` for Club and UserClub tables

### 2. Services & Infrastructure ✅

**SubdomainService:**
- Extracts current subdomain from request
- Helper methods: `isWwwSubdomain()`, `isAppSubdomain()`, `isClubSubdomain()`, `getClubSubdomain()`
- URL generation: `generateAppUrl()`, `generateWwwUrl()`, `generateClubUrl()`

**ClubResolver:**
- Resolves Club entity from subdomain
- Caches resolved club in request attributes
- Throws NotFoundHttpException if club not found or inactive

**ClubVoter:**
- Supports actions: `VIEW`, `MANAGE`, `INSPECT`
- Checks user has access to club and appropriate flags
- Admins have full access

### 3. Controllers Reorganization ✅

**App Subdomain (Admin):**
- `App/UserController` - User management (host: `app.{domain}`)
- `App/ClubController` - Club CRUD operations (host: `app.{domain}`)
- `App/ProfileController` - User profile management (host: `app.{domain}`)
- `App/DashboardController` - Admin dashboard (host: `app.{domain}`)

**Public Subdomain:**
- `Public/LandingController` - Homepage (host: `www.{domain}`)
- `Public/SecurityController` - Login, logout, registration, password reset (host: `www.{domain}`)

**Club Subdomain:**
- `Club/DashboardController` - Club-specific dashboard (host: `{subdomain}.{domain}`)

**Deleted:**
- Old `HomeController`, `UserController`, `ProfileController`, `RegistrationController`, `ResetPasswordController`

### 4. Templates Reorganization ✅

**Public Templates:**
- `public/base.html.twig` - Minimal layout for public pages
- `public/landing.html.twig` - Landing page
- `public/security/login.html.twig` - Login form
- `public/security/register.html.twig` - Registration form
- `public/security/reset_password/` - Password reset templates

**App Templates:**
- `app/base.html.twig` - Admin layout with navigation
- `app/dashboard.html.twig` - Admin dashboard
- `app/user/` - User management templates
- `app/club/` - Club management templates (index, new, show, edit, members)
- `app/profile/` - Profile templates

**Club Templates:**
- `club/base.html.twig` - Club layout with club-specific navigation
- `club/dashboard.html.twig` - Club dashboard

### 5. Forms & Validation ✅

**New Forms:**
- `ClubType` - For creating/editing clubs
- `Filter/ClubFilterType` - For filtering clubs list

### 6. Configuration Updates ✅

**Security (security.yaml):**
- Simplified role hierarchy: only `ROLE_ADMIN` → `ROLE_USER`
- Updated login/logout paths to use `public_` prefix
- Updated access control for subdomain patterns

**Routing (routing.yaml):**
- Added domain parameter configuration

**Services (services.yaml):**
- Registered SubdomainService with domain parameter

**Environment:**
- Added `MESSENGER_TRANSPORT_DSN` variable

## Key Features

### Access Control
- **Admins (ROLE_ADMIN)**: Can access app.domain and all club subdomains
- **Regular Users (ROLE_USER)**: Can only access club subdomains they're assigned to
- **Club-specific roles**: `isManager` and `isInspector` flags per club membership

### Subdomain Routing
- Route attributes include host requirements:
  - `host: 'app.{domain}'` for admin area
  - `host: 'www.{domain}'` for public area
  - `host: '{subdomain}.{domain}'` with regex `requirements: ['subdomain' => '(?!www|app).*']` for clubs

### Club Resolution
- ClubResolver automatically resolves club from subdomain
- Accessible in controllers via `$request->attributes->get('_club')`
- Throws 404 if club not found or inactive

### Mobile-First Design
- All templates follow mobile-first principles
- Touch-friendly buttons (min 44px)
- Responsive layouts using Tabler.io components
- Progressive enhancement for larger screens

## Next Steps (Not Implemented)

1. **User Assignment to Clubs:**
   - Form to assign users to clubs with manager/inspector flags
   - Update InvitationType to include club selection

2. **Club Member Management:**
   - Complete the member addition form in ClubController
   - Ability to edit user roles within a club

3. **Testing:**
   - Test subdomain resolution
   - Test access control for different user types
   - Test club-specific functionality

4. **Domain Configuration:**
   - Set up DNS for subdomain wildcards
   - Configure web server for subdomain routing
   - Set `DOMAIN` environment variable to actual domain

## Usage Examples

### Creating a Club
1. Login as admin at `app.domain`
2. Navigate to "Gérer les clubs"
3. Click "Nouveau club"
4. Fill in name (e.g., "Les Planeurs de Bailleau"), subdomain (e.g., "cvve"), description
5. Save

### Accessing a Club
1. Navigate to `cvve.domain`
2. ClubResolver automatically resolves the club
3. User must have access to this club (via UserClub relationship)
4. Dashboard shows club-specific content

### Assigning Users to Clubs
- Currently requires manual database manipulation or implementation of the member addition form
- UserClub entity stores the relationship with manager/inspector flags

## Database Schema

```
club
- id (PK)
- name
- subdomain (UNIQUE)
- description
- active
- created_at

user_club
- id (PK)
- user_id (FK → user)
- club_id (FK → club)
- is_manager
- is_inspector
- joined_at
- created_at
- UNIQUE(user_id, club_id)
```

## Environment Variables Required

```env
DOMAIN=localhost  # or your actual domain
MESSENGER_TRANSPORT_DSN=doctrine://default
DATABASE_URL=mysql://...
```

## Notes

- All role-based access control (manager/inspector) is now club-specific
- The old ROLE_MANAGER and ROLE_INSPECTOR roles have been removed
- Admins can access everything, regular users only their assigned clubs
- Mobile-first design maintained throughout all templates
- No linting errors in the codebase

