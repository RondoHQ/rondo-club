# Phase 58: React DOM Error Fix - Research

**Researched:** 2026-01-15
**Domain:** React DOM reconciliation errors (removeChild/insertBefore)
**Confidence:** HIGH

<research_summary>
## Summary

Researched the recurring `NotFoundError: Failed to execute 'removeChild' on 'Node'` errors in Stadion. This is a well-documented React issue ([GitHub #17256](https://github.com/facebook/react/issues/17256)) caused when React's virtual DOM becomes out of sync with the actual DOM.

The error occurs when React tries to remove a DOM node that no longer exists in its expected parent position. This happens because external forces (browser extensions, third-party scripts, translation features, or direct DOM manipulation) modify the DOM independently of React.

**Key findings specific to Stadion:**
1. The app uses React 18 with StrictMode (double-rendering in dev)
2. Fragment syntax (`<>...</>`) is used in App.jsx and 10 other components
3. Modals return early with `if (!isOpen) return null` - this is correct
4. `dangerouslySetInnerHTML` is used in TodoModal and TimelineView
5. No portals are used (good - one less source of issues)

**Primary recommendation:** Implement a multi-layered defense:
1. Add `<html translate="no">` to prevent Google Translate interference
2. Replace top-level Fragment in App.jsx with a wrapper div
3. Add DOM error boundary for graceful recovery
4. Consider monkey-patching removeChild as last resort for production
</research_summary>

<standard_stack>
## Standard Stack

No new libraries needed - this is a fix using React patterns.

### Core (Already Present)
| Library | Version | Purpose | Status |
|---------|---------|---------|--------|
| react | 18.x | UI framework | Already installed |
| react-dom | 18.x | DOM rendering | Already installed |
| react-error-boundary | 4.x | Error handling | Consider adding |

### Optional (If Needed)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| react-error-boundary | 4.0.13 | Declarative error handling | If custom error boundary is insufficient |

**Installation (if needed):**
```bash
npm install react-error-boundary
```
</standard_stack>

<architecture_patterns>
## Architecture Patterns

### Pattern 1: Prevent External DOM Modification
**What:** Block common external DOM modifiers at the HTML level
**When to use:** Always - preventive measure
**Example:**
```html
<!-- In index.html or server template -->
<html lang="en" translate="no">
  <head>
    <meta name="google" content="notranslate">
  </head>
</html>
```

### Pattern 2: Wrapper Div Instead of Fragment at App Root
**What:** Replace top-level Fragment with a stable container element
**When to use:** At the root App component return
**Why:** Fragments don't create DOM nodes, so browser extensions can more easily disrupt their children
**Example:**
```jsx
// Before (problematic)
function App() {
  return (
    <>
      <UpdateBanner />
      <Routes>...</Routes>
    </>
  );
}

// After (safer)
function App() {
  return (
    <div className="app-container">
      <UpdateBanner />
      <Routes>...</Routes>
    </div>
  );
}
```

### Pattern 3: DOM Error Boundary
**What:** Custom error boundary that catches and recovers from DOM sync errors
**When to use:** Wrap the entire app or specific problematic subtrees
**Example:**
```jsx
// src/components/DomErrorBoundary.jsx
import { Component } from 'react';

class DomErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, errorKey: 0 };
  }

  static getDerivedStateFromError(error) {
    // Only catch DOM-related errors
    if (
      error.name === 'NotFoundError' ||
      error.message?.includes('removeChild') ||
      error.message?.includes('insertBefore')
    ) {
      console.warn('DOM sync error caught, recovering:', error.message);
      return { hasError: true };
    }
    // Re-throw other errors
    throw error;
  }

  componentDidCatch(error, errorInfo) {
    // Log for debugging but don't crash
    console.error('DomErrorBoundary caught:', error, errorInfo);

    // Auto-recover after brief delay
    setTimeout(() => {
      this.setState(prev => ({
        hasError: false,
        errorKey: prev.errorKey + 1
      }));
    }, 100);
  }

  render() {
    if (this.state.hasError) {
      // Return null briefly during recovery, or a fallback UI
      return this.props.fallback || null;
    }
    return (
      <div key={this.state.errorKey}>
        {this.props.children}
      </div>
    );
  }
}

export default DomErrorBoundary;
```

### Pattern 4: Monkey Patch (Last Resort)
**What:** Override Node.prototype.removeChild to handle errors gracefully
**When to use:** Only if errors persist in production after other fixes
**Warning:** This is a workaround, not a fix
**Example:**
```jsx
// In main.jsx, before ReactDOM.createRoot
if (typeof Node !== 'undefined') {
  const originalRemoveChild = Node.prototype.removeChild;
  Node.prototype.removeChild = function(child) {
    if (child.parentNode !== this) {
      console.warn('removeChild called on wrong parent, using child.remove()');
      if (child.parentNode) {
        child.parentNode.removeChild(child);
      }
      return child;
    }
    return originalRemoveChild.call(this, child);
  };

  const originalInsertBefore = Node.prototype.insertBefore;
  Node.prototype.insertBefore = function(newNode, referenceNode) {
    if (referenceNode && referenceNode.parentNode !== this) {
      console.warn('insertBefore reference node not in parent');
      return this.appendChild(newNode);
    }
    return originalInsertBefore.call(this, newNode, referenceNode);
  };
}
```

### Anti-Patterns to Avoid
- **Direct DOM manipulation with React:** Never use `document.getElementById().remove()` while React manages that element
- **Mixing jQuery with React:** Don't use jQuery to modify elements React is rendering
- **Third-party scripts in React-managed areas:** Keep analytics/tracking scripts in `<head>`, not in component trees
</architecture_patterns>

<dont_hand_roll>
## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Error boundaries | Custom try-catch in render | React Error Boundary class | React has specific lifecycle methods for errors |
| DOM node tracking | Manual ref tracking | Let React manage DOM | React's reconciliation handles this |
| Translation blocking | JavaScript detection | HTML `translate="no"` attribute | Browser-level is more reliable |
| Re-render forcing | `forceUpdate()` hacks | Key prop changes | Key changes trigger clean re-mount |

**Key insight:** These errors are caused by external factors breaking React's assumptions. The fix is defensive programming (preventing interference) and graceful degradation (catching errors), not trying to fix React's internals.
</dont_hand_roll>

<common_pitfalls>
## Common Pitfalls

### Pitfall 1: Assuming StrictMode Causes Production Errors
**What goes wrong:** Developers disable StrictMode thinking it causes these errors
**Why it happens:** StrictMode double-renders in dev, making timing issues more visible
**How to avoid:** Keep StrictMode - it reveals real bugs. The errors still happen in production without it.
**Warning signs:** Errors only appearing intermittently in production despite "fixing" in dev

### Pitfall 2: Wrapping Everything in Error Boundaries
**What goes wrong:** Error boundaries swallow legitimate errors
**Why it happens:** Over-broad error catching
**How to avoid:** Only catch specific DOM-related errors (NotFoundError, removeChild, insertBefore)
**Warning signs:** Other bugs going unnoticed because they're being silently caught

### Pitfall 3: Using Index as Key in Lists
**What goes wrong:** React loses track of elements when list order changes
**Why it happens:** Index keys don't provide stable identity
**How to avoid:** Use unique IDs from data as keys
**Warning signs:** List items re-rendering incorrectly after sort/filter operations

### Pitfall 4: Conditional Rendering with Fragments
**What goes wrong:** Fragment children get orphaned when external code modifies DOM
**Why it happens:** Fragments don't create a container node for React to track
**How to avoid:** Use wrapper divs for conditionally rendered groups at higher levels
**Warning signs:** Errors specifically mentioning text nodes or #text

### Pitfall 5: Direct DOM Access in useEffect Cleanup
**What goes wrong:** Cleanup runs after element already removed
**Why it happens:** React's cleanup timing vs actual DOM state
**How to avoid:** Check if element exists before manipulating: `if (ref.current) { ... }`
**Warning signs:** Errors during navigation or component unmounting
</common_pitfalls>

<code_examples>
## Code Examples

### Recommended main.jsx Structure
```jsx
// Source: Stadion pattern + research recommendations
import React from 'react';
import ReactDOM from 'react-dom/client';
import DomErrorBoundary from './components/DomErrorBoundary';

// Optional: Monkey patch as safety net (uncomment if needed in production)
// if (typeof Node !== 'undefined') {
//   const originalRemoveChild = Node.prototype.removeChild;
//   Node.prototype.removeChild = function(child) {
//     if (child.parentNode !== this) {
//       console.warn('DOM sync issue, recovering');
//       return child.parentNode ? child.parentNode.removeChild(child) : child;
//     }
//     return originalRemoveChild.call(this, child);
//   };
// }

const rootElement = document.getElementById('root');

if (rootElement) {
  ReactDOM.createRoot(rootElement).render(
    <React.StrictMode>
      <DomErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <BrowserRouter>
            <App />
          </BrowserRouter>
        </QueryClientProvider>
      </DomErrorBoundary>
    </React.StrictMode>
  );
}
```

### Safe Modal Pattern (Already Correct in Stadion)
```jsx
// Source: Existing TodoModal pattern - this is correct
export default function TodoModal({ isOpen, onClose, ... }) {
  // Early return prevents rendering when not open
  // This is the RIGHT way to do conditional modals
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 ...">
      {/* Modal content */}
    </div>
  );
}
```

### Safe dangerouslySetInnerHTML Usage
```jsx
// Source: React docs pattern
// Current Stadion usage is correct - the issue isn't here
<div
  className="prose"
  dangerouslySetInnerHTML={{ __html: sanitizedHtml }}
/>
```
</code_examples>

<sota_updates>
## State of the Art (2025-2026)

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Ignore these errors | Defensive boundaries + prevention | 2024+ | Proactive error handling |
| Disable StrictMode | Keep StrictMode, fix root cause | React 18 | Better debugging |
| jQuery + React mixing | Pure React | Long-standing | Avoids DOM conflicts |

**New patterns to consider:**
- **Error boundaries with recovery:** Modern approach uses auto-recovery after DOM sync errors
- **Translation blocking at HTML level:** More reliable than JavaScript-based detection

**Deprecated/outdated:**
- **Suppressing console errors:** Hiding errors doesn't fix them
- **`suppressHydrationWarning`:** Only for SSR, not relevant to client-side DOM errors
</sota_updates>

<open_questions>
## Open Questions

1. **Which component is the actual source?**
   - What we know: Error happens in production, stack trace is minified
   - What's unclear: Exact component causing the issue
   - Recommendation: Add source maps temporarily or use React DevTools Profiler to identify hot spots

2. **Is Google Translate the culprit?**
   - What we know: Google Translate is a common cause of these errors
   - What's unclear: Whether Stadion users have it enabled
   - Recommendation: Add `translate="no"` regardless - it's harmless and preventive

3. **Browser extension interference?**
   - What we know: Extensions like password managers can modify DOM
   - What's unclear: Which extensions users have
   - Recommendation: Error boundary will catch regardless of cause
</open_questions>

<sources>
## Sources

### Primary (HIGH confidence)
- [GitHub React Issue #17256](https://github.com/facebook/react/issues/17256) - Official issue tracker, confirmed patterns
- [GitHub React Issue #6593](https://github.com/facebook/react/issues/6593) - Third-party library DOM manipulation
- [30 Seconds of Code - Breaking React](https://www.30secondsofcode.org/react/s/breaking-react/) - Common anti-patterns

### Secondary (MEDIUM confidence)
- [React Error Boundaries Docs](https://legacy.reactjs.org/docs/error-boundaries.html) - Official documentation
- WebSearch findings on DomErrorBoundary patterns - Verified against official error boundary docs
- React Router Issue #11110 - Similar issue pattern, same solutions apply

### Tertiary (LOW confidence - needs validation during implementation)
- Monkey patch approach - Community solution, works but is a workaround
- Auto-recovery timing (100ms delay) - May need tuning based on actual behavior
</sources>

<metadata>
## Metadata

**Research scope:**
- Core technology: React 18 DOM reconciliation
- Ecosystem: Error boundaries, defensive patterns
- Patterns: Fragment vs div, conditional rendering, error recovery
- Pitfalls: External DOM modification, StrictMode misconceptions

**Confidence breakdown:**
- Root cause identification: HIGH - Well-documented React issue
- Prevention strategies: HIGH - translate="no" and wrapper divs are proven
- Error boundary pattern: HIGH - Standard React pattern
- Monkey patch: MEDIUM - Works but is a workaround, not a fix

**Research date:** 2026-01-15
**Valid until:** 2026-03-15 (60 days - React patterns are stable)
</metadata>

---

*Phase: 58-react-dom-error-fix*
*Research completed: 2026-01-15*
*Ready for planning: yes*
