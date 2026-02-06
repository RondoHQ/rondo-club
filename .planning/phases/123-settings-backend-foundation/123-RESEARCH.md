# Phase 123: Settings & Backend Foundation - Research

**Researched:** 2026-01-31
**Domain:** WordPress Options API + React Settings UI
**Confidence:** HIGH

## Summary

This phase establishes the foundation for the membership fees system by creating admin settings to configure fee amounts. The implementation requires both backend (PHP) and frontend (React) components, following established WordPress and Stadion patterns.

The codebase already has extensive settings infrastructure in place. The Settings page (`src/pages/Settings/Settings.jsx`) uses a tab-based layout with support for subtabs within tabs (e.g., Connections tab has Calendar, Contacts, CardDAV, Slack subtabs). WordPress Options API is used for storing settings, with REST API endpoints for reading/writing, and React forms for the UI.

This phase needs to create a new "Contributie" (Contribution/Fees) subtab under an appropriate parent tab (likely Admin tab since fee configuration is administrative), implement REST API endpoints for reading/writing fee amounts, and create a calculation service class that can be used by other phases.

**Primary recommendation:** Use WordPress Options API with a single option array for all fee amounts, REST API endpoint pattern similar to notification settings, and React form with number inputs following the subtab pattern already used in Connections tab.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | 6.0+ | Store settings in wp_options table | Native WordPress persistence layer for configuration data |
| WordPress REST API | 5.0+ | HTTP endpoints for settings CRUD | Standard way to expose WordPress data to React frontend |
| React Hook Form | Latest | Form state management | Codebase doesn't currently use this, but it's industry standard for 2026 |
| React | 18 | UI components | Already in use throughout Stadion |
| TanStack Query | Latest | Server state/caching | Already used for API calls in Stadion |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Zod | Latest | Schema validation | Optional: for type-safe validation if adding TypeScript |
| DOMPurify | Latest | Sanitize user input | Not needed for number inputs, already handled server-side |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Options API | Custom database table | Options API is simpler for small settings, custom table only needed for high-volume queries |
| Single array option | Individual options per fee | Array reduces autoload overhead (one DB row vs six) |
| REST endpoint | Admin-AJAX | REST API is more modern, standardized, and used throughout Stadion |

**Installation:**
```bash
# No new dependencies needed - all libraries already in place
# React Hook Form would be added if implementing:
npm install react-hook-form
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-membership-fees.php    # New: Fee calculation service class
└── class-rest-api.php            # Extend: Add fee settings endpoints

src/pages/Settings/
└── Settings.jsx                  # Extend: Add Contributie subtab to Admin tab
```

### Pattern 1: WordPress Options Storage
**What:** Store all membership fee amounts in a single array option
**When to use:** Small related settings that are always loaded together
**Example:**
```php
// Source: WordPress Options API documentation
// Store fees as array
update_option( 'stadion_membership_fees', [
    'mini'     => 130,
    'pupil'    => 180,
    'junior'   => 230,
    'senior'   => 255,
    'recreant' => 65,
    'donateur' => 55,
] );

// Retrieve with defaults
$fees = get_option( 'stadion_membership_fees', [
    'mini'     => 130,
    'pupil'    => 180,
    'junior'   => 230,
    'senior'   => 255,
    'recreant' => 65,
    'donateur' => 55,
] );
```

### Pattern 2: REST API Endpoint for Settings
**What:** Create dedicated endpoints for reading and updating settings
**When to use:** When React frontend needs to manage WordPress options
**Example:**
```php
// Source: Stadion includes/class-rest-api.php (notification channels pattern)
// In class-rest-api.php, add to register_routes()
register_rest_route(
    'rondo/v1',
    '/membership-fees',
    [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_membership_fees' ],
        'permission_callback' => [ $this, 'check_admin_permission' ],
    ]
);

register_rest_route(
    'rondo/v1',
    '/membership-fees',
    [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'update_membership_fees' ],
        'permission_callback' => [ $this, 'check_admin_permission' ],
        'args'                => [
            'fees' => [
                'required'          => true,
                'validate_callback' => function ( $param ) {
                    return is_array( $param )
                        && isset( $param['mini'], $param['pupil'], $param['junior'],
                                  $param['senior'], $param['recreant'], $param['donateur'] )
                        && array_reduce( $param, fn($carry, $val) => $carry && is_numeric($val) && $val >= 0, true );
                },
            ],
        ],
    ]
);

public function get_membership_fees( $request ) {
    $fees = get_option( 'stadion_membership_fees', [
        'mini'     => 130,
        'pupil'    => 180,
        'junior'   => 230,
        'senior'   => 255,
        'recreant' => 65,
        'donateur' => 55,
    ] );

    return rest_ensure_response( [ 'fees' => $fees ] );
}

public function update_membership_fees( $request ) {
    $fees = $request->get_param( 'fees' );

    // Sanitize all values as positive integers
    $sanitized_fees = array_map( 'absint', $fees );

    $updated = update_option( 'stadion_membership_fees', $sanitized_fees );

    if ( $updated ) {
        return rest_ensure_response( [
            'success' => true,
            'fees' => $sanitized_fees
        ] );
    }

    return new WP_Error(
        'update_failed',
        __( 'Failed to update membership fees.', 'stadion' ),
        [ 'status' => 500 ]
    );
}
```

### Pattern 3: React Subtab Component
**What:** Add a subtab to an existing Settings tab following the established pattern
**When to use:** When grouping related settings under a parent tab
**Example:**
```javascript
// Source: Stadion src/pages/Settings/Settings.jsx (CONNECTION_SUBTABS pattern)

// Add to TABS configuration (modify Admin tab to support subtabs)
const ADMIN_SUBTABS = [
  { id: 'general', label: 'Algemeen', icon: Settings },
  { id: 'contributie', label: 'Contributie', icon: FileCode },
];

// Create subtab component
function AdminContributieSubtab({ fees, updateFees, saving }) {
  const [formData, setFormData] = useState(fees);

  const handleChange = (type, value) => {
    setFormData(prev => ({ ...prev, [type]: parseInt(value) || 0 }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    await updateFees(formData);
  };

  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4">Contributiebedragen</h2>
      <form onSubmit={handleSubmit} className="space-y-4">
        {Object.entries(FEE_TYPES).map(([key, label]) => (
          <div key={key}>
            <label className="block text-sm font-medium mb-1">
              {label}
            </label>
            <input
              type="number"
              min="0"
              step="1"
              value={formData[key]}
              onChange={(e) => handleChange(key, e.target.value)}
              className="input w-32"
            />
            <span className="ml-2 text-sm text-gray-500">€</span>
          </div>
        ))}
        <button type="submit" disabled={saving} className="btn-primary">
          {saving ? 'Opslaan...' : 'Opslaan'}
        </button>
      </form>
    </div>
  );
}
```

### Pattern 4: Calculation Service Class
**What:** Create a reusable service class for fee calculations
**When to use:** Business logic that needs to be called from multiple places (REST API, WP-CLI, cron jobs)
**Example:**
```php
// Source: Stadion patterns (similar to class-reminders.php service pattern)
// includes/class-membership-fees.php
namespace Stadion\Membership;

class Fees {
    /**
     * Get configured fee amounts
     */
    public function get_fees() {
        return get_option( 'stadion_membership_fees', [
            'mini'     => 130,
            'pupil'    => 180,
            'junior'   => 230,
            'senior'   => 255,
            'recreant' => 65,
            'donateur' => 55,
        ] );
    }

    /**
     * Get fee amount for specific type
     */
    public function get_fee_amount( $type ) {
        $fees = $this->get_fees();
        return isset( $fees[ $type ] ) ? $fees[ $type ] : 0;
    }

    /**
     * Calculate total fee for a person
     * (Used in later phases)
     */
    public function calculate_person_fee( $person_id ) {
        // Placeholder for later implementation
        return 0;
    }
}
```

### Anti-Patterns to Avoid
- **Individual options per fee:** Creates 6 autoloaded options instead of 1, increases database overhead
- **Hardcoded fee amounts:** Makes it impossible for admins to adjust without code changes
- **No validation on REST endpoint:** Could allow negative or non-numeric values to be saved
- **Client-side only validation:** Server must always validate and sanitize user input

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Form validation | Custom validation logic | WordPress validate_callback on register_rest_route | Built-in, standardized, prevents bypassing client-side validation |
| Input sanitization | Manual type casting | WordPress absint(), sanitize_text_field() | Handles edge cases, security-tested |
| Option caching | Custom cache layer | WordPress Options API built-in caching | Already optimized, uses wp_cache |
| Permission checks | Custom user role checks | WordPress check_admin_permission() pattern | Respects WordPress capabilities system |
| REST response formatting | Manual JSON encoding | rest_ensure_response() | Handles proper headers, CORS, error formatting |

**Key insight:** WordPress has battle-tested APIs for all these operations. Custom solutions introduce security risks and maintenance burden.

## Common Pitfalls

### Pitfall 1: Autoload Bloat
**What goes wrong:** Creating individual options for each fee type with autoload=yes
**Why it happens:** Default behavior of update_option() is autoload=yes
**How to avoid:** Use a single array option for related settings
**Warning signs:** wp_options table has many stadion_* rows, slow page loads

### Pitfall 2: Missing Server-side Validation
**What goes wrong:** Trusting client-side validation, allowing invalid data to be saved
**Why it happens:** Forgetting that REST API can be called directly (not just from React UI)
**How to avoid:** Always use validate_callback and sanitize in REST endpoint
**Warning signs:** Negative numbers, strings, or null values in fee settings

### Pitfall 3: Not Using Default Values
**What goes wrong:** get_option() returns false when option doesn't exist, breaking calculations
**Why it happens:** Forgetting to provide default parameter to get_option()
**How to avoid:** Always specify defaults matching the initial configuration
**Warning signs:** Fatal errors or zero fees before admin configures settings

### Pitfall 4: Direct Option Updates Outside API
**What goes wrong:** Updating options via SQL or other means bypasses WordPress cache
**Why it happens:** Trying to "optimize" or batch update settings
**How to avoid:** Always use update_option(), which handles cache invalidation
**Warning signs:** Settings changes don't appear until cache is manually cleared

### Pitfall 5: Non-numeric Input Handling
**What goes wrong:** Input fields allow decimal points or non-numeric characters
**Why it happens:** Using type="number" without step or validation
**How to avoid:** Use step="1" min="0" and parseInt() on change, validate server-side
**Warning signs:** Fees stored as 130.50 when only integers expected

## Code Examples

Verified patterns from official sources:

### Reading Options with Defaults
```php
// Source: WordPress Options API documentation
$fees = get_option( 'stadion_membership_fees', [
    'mini'     => 130,
    'pupil'    => 180,
    'junior'   => 230,
    'senior'   => 255,
    'recreant' => 65,
    'donateur' => 55,
] );

// Access individual fee
$mini_fee = $fees['mini']; // Always returns a value, never undefined
```

### Updating Options Safely
```php
// Source: WordPress Options API documentation
// Sanitize first
$new_fees = [
    'mini'     => absint( $_POST['mini'] ),
    'pupil'    => absint( $_POST['pupil'] ),
    'junior'   => absint( $_POST['junior'] ),
    'senior'   => absint( $_POST['senior'] ),
    'recreant' => absint( $_POST['recreant'] ),
    'donateur' => absint( $_POST['donateur'] ),
];

// update_option creates if missing, updates if exists
$updated = update_option( 'stadion_membership_fees', $new_fees );

// Returns false if value unchanged, true if updated
if ( $updated ) {
    // Setting was changed
}
```

### React Number Input Best Practices
```javascript
// Source: React form validation best practices 2026
<input
  type="number"
  min="0"
  step="1"
  value={feeAmount}
  onChange={(e) => {
    // Parse to integer, default to 0 if invalid
    const value = parseInt(e.target.value) || 0;
    setFeeAmount(value);
  }}
  className="input"
/>
```

### TanStack Query for Settings
```javascript
// Source: Stadion existing patterns (src/pages/Settings/Settings.jsx)
const { data: fees, isLoading } = useQuery({
  queryKey: ['membership-fees'],
  queryFn: async () => {
    const response = await prmApi.get('/rondo/v1/membership-fees');
    return response.data.fees;
  },
});

const updateFeesMutation = useMutation({
  mutationFn: async (fees) => {
    const response = await prmApi.post('/rondo/v1/membership-fees', { fees });
    return response.data;
  },
  onSuccess: () => {
    queryClient.invalidateQueries(['membership-fees']);
  },
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate options per setting | Array option for related settings | WordPress 4.0+ | Reduces autoload overhead, simpler to manage |
| Admin-AJAX for settings | REST API endpoints | WordPress 5.0+ | Better structure, testable, auto-documented |
| jQuery forms | React Hook Form | 2020+ | Better performance, less re-renders, type safety |
| Manual validation | Schema-based (Zod/Yup) | 2021+ | Type safety, reusable schemas, less boilerplate |

**Deprecated/outdated:**
- Settings API (settings_fields(), do_settings_sections()): Too complex for simple settings, REST API preferred for React frontends
- Storing each setting separately: Causes autoload bloat for related settings

## Open Questions

1. **Should fees support decimal amounts (e.g., €130.50)?**
   - What we know: Current requirements specify integer amounts (130, 180, etc.)
   - What's unclear: Future need for cents precision
   - Recommendation: Start with integers (absint validation), can migrate to floats later if needed

2. **Should there be fee history tracking?**
   - What we know: Not mentioned in requirements SET-01 through SET-06
   - What's unclear: Whether admins need to see when fees changed
   - Recommendation: YAGNI - don't implement until requested, easy to add later

3. **Where does Contributie subtab belong?**
   - What we know: Admin tab exists and is admin-only
   - What's unclear: Whether fees should be separate tab or Admin subtab
   - Recommendation: Admin subtab (keeps settings organized, already has admin permission check)

## Sources

### Primary (HIGH confidence)
- [WordPress Options API Documentation](https://developer.wordpress.org/apis/options/) - Official WordPress docs on get_option/update_option
- [WordPress REST API: Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) - Official guide for register_rest_route
- Stadion codebase analysis (includes/class-rest-api.php, src/pages/Settings/Settings.jsx) - Existing patterns

### Secondary (MEDIUM confidence)
- [Mastering the WordPress Options API](https://wpshout.com/mastering-wordpress-options-api/) - Best practices for Options API
- [React Form Validation Best Practices](https://www.dhiwise.com/post/react-form-validation-best-practices-with-tips-and-tricks) - Modern React form patterns
- [React Hook Form combined with Zod](https://blog.logrocket.com/react-form-validation-sollutions-ultimate-roundup/) - Modern validation approach (optional enhancement)

### Tertiary (LOW confidence)
- None - all research verified with primary sources

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, WordPress APIs well-documented
- Architecture: HIGH - Patterns verified in existing Stadion codebase
- Pitfalls: HIGH - Based on WordPress Options API documentation and common developer mistakes

**Research date:** 2026-01-31
**Valid until:** 2026-03-31 (60 days - WordPress APIs and React patterns are stable)
