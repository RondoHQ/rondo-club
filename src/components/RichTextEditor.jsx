import { useRef, useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import {
  Bold,
  Italic,
  List,
  ListOrdered,
  Link as LinkIcon,
  Unlink,
  Image as ImageIcon,
  Undo,
  Redo,
  Loader2,
} from 'lucide-react';
import { wpApi } from '../api/client';

const containsHtmlMarkup = (value) => /<\s*[a-z!/][^>]*>/i.test(value);

const escapeHtml = (value) => value
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;');

const normalizeEditorContent = (value) => {
  if (!value) {
    return '';
  }

  if (containsHtmlMarkup(value)) {
    return value;
  }

  const normalized = value.replace(/\r\n?/g, '\n').trim();
  if (!normalized) {
    return '';
  }

  return normalized
    .split(/\n{2,}/)
    .map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`)
    .join('');
};

const MenuButton = ({ onClick, isActive, disabled, children, title }) => (
  <button
    type="button"
    onClick={onClick}
    disabled={disabled}
    title={title}
    className={`p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors ${
      isActive ? 'bg-gray-200 dark:bg-gray-600 text-electric-cyan dark:text-electric-cyan' : 'text-gray-600 dark:text-gray-400'
    } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
  >
    {children}
  </button>
);

const MenuBar = ({ editor, enableImages = false }) => {
  const fileInputRef = useRef(null);
  const [isUploading, setIsUploading] = useState(false);

  if (!editor) return null;

  const handleImageFile = async (event) => {
    const file = event.target.files?.[0];
    if (fileInputRef.current) fileInputRef.current.value = '';
    if (!file) return;

    setIsUploading(true);
    try {
      const response = await wpApi.uploadMedia(file);
      const src = response.data?.source_url;
      if (src) {
        editor.chain().focus().setImage({ src, alt: file.name }).run();
      }
    } catch (error) {
      console.error('Afbeelding uploaden mislukt:', error);
      alert('Afbeelding uploaden mislukt. Probeer het opnieuw.');
    } finally {
      setIsUploading(false);
    }
  };

  const setLink = () => {
    const previousUrl = editor.getAttributes('link').href;
    const url = window.prompt('Voer URL in:', previousUrl);

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
        title="Vet (Cmd+B)"
      >
        <Bold className="w-4 h-4" />
      </MenuButton>

      <MenuButton
        onClick={() => editor.chain().focus().toggleItalic().run()}
        isActive={editor.isActive('italic')}
        title="Cursief (Cmd+I)"
      >
        <Italic className="w-4 h-4" />
      </MenuButton>

      <div className="w-px h-4 bg-gray-300 dark:bg-gray-500 mx-1" />

      <MenuButton
        onClick={() => editor.chain().focus().toggleBulletList().run()}
        isActive={editor.isActive('bulletList')}
        title="Opsommingslijst"
      >
        <List className="w-4 h-4" />
      </MenuButton>

      <MenuButton
        onClick={() => editor.chain().focus().toggleOrderedList().run()}
        isActive={editor.isActive('orderedList')}
        title="Genummerde lijst"
      >
        <ListOrdered className="w-4 h-4" />
      </MenuButton>

      <div className="w-px h-4 bg-gray-300 dark:bg-gray-500 mx-1" />

      <MenuButton
        onClick={setLink}
        isActive={editor.isActive('link')}
        title="Link toevoegen"
      >
        <LinkIcon className="w-4 h-4" />
      </MenuButton>

      {editor.isActive('link') && (
        <MenuButton
          onClick={() => editor.chain().focus().unsetLink().run()}
          title="Link verwijderen"
        >
          <Unlink className="w-4 h-4" />
        </MenuButton>
      )}

      {enableImages && (
        <>
          <div className="w-px h-4 bg-gray-300 dark:bg-gray-500 mx-1" />
          <MenuButton
            onClick={() => fileInputRef.current?.click()}
            disabled={isUploading}
            title="Afbeelding toevoegen"
          >
            {isUploading ? (
              <Loader2 className="w-4 h-4 animate-spin" />
            ) : (
              <ImageIcon className="w-4 h-4" />
            )}
          </MenuButton>
          <input
            ref={fileInputRef}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={handleImageFile}
          />
        </>
      )}

      <div className="flex-1" />

      <MenuButton
        onClick={() => editor.chain().focus().undo().run()}
        disabled={!editor.can().undo()}
        title="Ongedaan maken (Cmd+Z)"
      >
        <Undo className="w-4 h-4" />
      </MenuButton>

      <MenuButton
        onClick={() => editor.chain().focus().redo().run()}
        disabled={!editor.can().redo()}
        title="Opnieuw (Cmd+Shift+Z)"
      >
        <Redo className="w-4 h-4" />
      </MenuButton>
    </div>
  );
};

export default function RichTextEditor({
  value = '',
  onChange,
  placeholder = 'Schrijf iets...',
  disabled = false,
  minHeight = '120px',
  autoFocus = false,
  enableImages = false,
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
          class: 'text-electric-cyan hover:text-bright-cobalt underline',
        },
      }),
      // Inline images (opt-in) — uploaded to /wp/v2/media, stored as <img> in
      // the HTML body. Off by default so notes/activities are unaffected.
      ...(enableImages
        ? [
            Image.configure({
              inline: false,
              allowBase64: false,
              HTMLAttributes: {
                class: 'rounded-md max-w-full h-auto my-2',
              },
            }),
          ]
        : []),
    ],
    content: normalizeEditorContent(value),
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
  const normalizedValue = normalizeEditorContent(value);
  if (editor && normalizedValue !== editor.getHTML() && normalizedValue !== '') {
    // Avoid infinite loops by checking if content is truly different
    const currentContent = editor.getHTML();
    const isEmpty = currentContent === '<p></p>' || currentContent === '';
    if (normalizedValue && (isEmpty || normalizedValue !== currentContent)) {
      editor.commands.setContent(normalizedValue, false);
    }
  }

  return (
    <div className={`border border-gray-300 dark:border-gray-600 rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-electric-cyan focus-within:border-transparent ${
      disabled ? 'bg-gray-100 dark:bg-gray-800 opacity-60' : 'bg-white dark:bg-gray-700'
    }`}>
      <MenuBar editor={editor} enableImages={enableImages} />
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
