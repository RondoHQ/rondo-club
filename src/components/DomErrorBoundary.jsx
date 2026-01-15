import { Component } from 'react';

/**
 * Error boundary that catches React DOM synchronization errors.
 *
 * These errors occur when external forces (browser extensions, Google Translate,
 * third-party scripts) modify the DOM independently of React, causing React's
 * virtual DOM to become out of sync with the actual DOM.
 *
 * This boundary catches these specific errors and auto-recovers by forcing
 * a clean re-render of its children.
 */
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
      console.warn('[DomErrorBoundary] Caught DOM sync error, recovering:', error.message);
      return { hasError: true };
    }
    // Re-throw other errors - don't swallow legitimate bugs
    throw error;
  }

  componentDidCatch(error, errorInfo) {
    // Log for debugging in production
    console.error('[DomErrorBoundary] Error details:', error, errorInfo);

    // Auto-recover after brief delay to let React stabilize
    setTimeout(() => {
      this.setState(prev => ({
        hasError: false,
        errorKey: prev.errorKey + 1
      }));
    }, 100);
  }

  render() {
    if (this.state.hasError) {
      // Return null briefly during recovery
      // The setTimeout in componentDidCatch will trigger re-render
      return null;
    }

    // Key change forces clean re-mount after recovery
    return (
      <div key={this.state.errorKey}>
        {this.props.children}
      </div>
    );
  }
}

export default DomErrorBoundary;
