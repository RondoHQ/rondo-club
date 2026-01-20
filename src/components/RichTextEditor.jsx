import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import { 
  Bold, 
  Italic, 
  List, 
  ListOrdered, 
  Link as LinkIcon,
  Unlink,
  Undo,
  Redo
} from 'lucide-react';

const MenuButton = ({ onClick, isActive, disabled, children, title }) => (
  <button
    type="button"
    onClick={onClick}
    disabled={disabled}
    title={title}
    className={`p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors ${
      isActive ? 'bg-gray-200 dark:bg-gray-600 text-accent-600 dark:text-accent-400' : 'text-gray-600 dark:text-gray-400'
    } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
  >
    {children}
  </button>
);

const MenuBar = ({ editor }) => {
  if (!editor) return null;

  const setLink = () => {
    const previousUrl = editor.getAttributes('link').href;
    const url = window.prompt('Enter URL:', previousUrl);

    if (url === null) return;

    if (url === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }

    // Add https:// if no protocol specified
    const finalUrl = url.match(/^https?:\/\//) ? url : `https://${url}`;
    editor.chain().focus().extendMarkRange('link').setLink({ href: finalUrl }).run();
  };

  return (
    <div className="flex items-center gap-0.5 p-1.5 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-t-md">
      <MenuButton
        onClick={() => editor.chain().focus().toggleBold().run()}
        isActive={editor.isActive('bold')}
        title="Bold (Cmd+B)"
      >
        <Bold className="w-4 h-4" />
      </MenuButton>

      <MenuButton
        onClick={() => editor.chain().focus().toggleItalic().run()}
        isActive={editor.isActive('italic')}
        title="Italic (Cmd+I)"
      >
        <Italic className="w-4 h-4" />
      </MenuButton>

      <div className="w-px h-4 bg-gray-300 dark:bg-gray-500 mx-1" />

      <MenuButton
        onClick={() => editor.chain().focus().toggleBulletList().run()}
        isActive={editor.isActive('bulletList')}
        title="Bullet list"
      >
        <List className="w-4 h-4" />
      </MenuButton>

      <MenuButton
        onClick={() => editor.chain().focus().toggleOrderedList().run()}
        isActive={editor.isActive('orderedList')}
        title="Numbered list"
      >
        <ListOrdered className="w-4 h-4" />
      </MenuButton>

      <div className="w-px h-4 bg-gray-300 dark:bg-gray-500 mx-1" />

      <MenuButton
        onClick={setLink}
        isActive={editor.isActive('link')}
        title="Add link"
      >
        <LinkIcon className="w-4 h-4" />
      </MenuButton>

      {editor.isActive('link') && (
        <MenuButton
          onClick={() => editor.chain().focus().unsetLink().run()}
          title="Remove link"
        >
          <Unlink className="w-4 h-4" />
        </MenuButton>
      )}

      <div className="flex-1" />

      <MenuButton
        onClick={() => editor.chain().focus().undo().run()}
        disabled={!editor.can().undo()}
        title="Undo (Cmd+Z)"
      >
        <Undo className="w-4 h-4" />
      </MenuButton>

      <MenuButton
        onClick={() => editor.chain().focus().redo().run()}
        disabled={!editor.can().redo()}
        title="Redo (Cmd+Shift+Z)"
      >
        <Redo className="w-4 h-4" />
      </MenuButton>
    </div>
  );
};

export default function RichTextEditor({ 
  value = '', 
  onChange, 
  placeholder = 'Write something...', 
  disabled = false,
  minHeight = '120px',
  autoFocus = false,
}) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: false, // Disable headings for notes/activities
        codeBlock: false, // Disable code blocks
        blockquote: false, // Disable blockquotes
        link: false, // Disable - we add Link separately with custom config below
      }),
      Placeholder.configure({
        placeholder,
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-accent-600 hover:text-accent-700 underline',
        },
      }),
    ],
    content: value,
    editable: !disabled,
    autofocus: autoFocus,
    onUpdate: ({ editor }) => {
      const html = editor.getHTML();
      // Return empty string if editor only has empty paragraph
      const isEmpty = html === '<p></p>' || html === '';
      onChange(isEmpty ? '' : html);
    },
  });

  // Update editor content when value prop changes (for edit mode)
  // Only update if editor exists and the content is different
  if (editor && value !== editor.getHTML() && value !== '') {
    // Avoid infinite loops by checking if content is truly different
    const currentContent = editor.getHTML();
    const isEmpty = currentContent === '<p></p>' || currentContent === '';
    if (value && (isEmpty || value !== currentContent)) {
      editor.commands.setContent(value, false);
    }
  }

  return (
    <div className={`border border-gray-300 dark:border-gray-600 rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-accent-500 focus-within:border-transparent ${
      disabled ? 'bg-gray-100 dark:bg-gray-800 opacity-60' : 'bg-white dark:bg-gray-700'
    }`}>
      <MenuBar editor={editor} />
      <EditorContent
        editor={editor}
        className="prose prose-sm dark:prose-invert max-w-none"
        style={{ minHeight }}
      />
      <style>{`
        .ProseMirror {
          padding: 0.75rem;
          min-height: ${minHeight};
          outline: none;
        }
        .dark .ProseMirror {
          color: #f9fafb;
        }
        .ProseMirror p.is-editor-empty:first-child::before {
          content: attr(data-placeholder);
          float: left;
          color: #9ca3af;
          pointer-events: none;
          height: 0;
        }
        .dark .ProseMirror p.is-editor-empty:first-child::before {
          color: #6b7280;
        }
        .ProseMirror ul,
        .ProseMirror ol {
          padding-left: 1.5rem;
        }
        .ProseMirror ul {
          list-style-type: disc;
        }
        .ProseMirror ol {
          list-style-type: decimal;
        }
        .ProseMirror li {
          margin-bottom: 0.25rem;
        }
        .ProseMirror p {
          margin-bottom: 0.5rem;
        }
        .ProseMirror p:last-child {
          margin-bottom: 0;
        }
      `}</style>
    </div>
  );
}

// Re-export utilities for backward compatibility
export { isRichTextEmpty, stripHtmlTags } from '@/utils/richTextUtils';

