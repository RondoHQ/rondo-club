import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Edit, Trash2, Star, Mail, Phone,
  MapPin, Globe, Building2, Calendar, Plus, Gift, Heart, Pencil
} from 'lucide-react';
import { usePerson, usePersonTimeline, usePersonDates, useDeletePerson } from '@/hooks/usePeople';
import { format, differenceInYears } from 'date-fns';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function PersonDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { data: person, isLoading, error } = usePerson(id);
  const { data: timeline } = usePersonTimeline(id);
  const { data: personDates } = usePersonDates(id);
  const deletePerson = useDeletePerson();
  
  // Update document title with person's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(person?.title?.rendered || person?.title || 'Person');

  // Find birthday from person dates
  // date_type is an array of term names from the API
  const birthday = personDates?.find(d => {
    const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
    return dateType?.toLowerCase() === 'birthday';
  });
  // The API returns date_value, not date
  const birthDate = birthday?.date_value ? new Date(birthday.date_value) : null;
  const age = birthDate ? differenceInYears(new Date(), birthDate) : null;

  // Get other dates (non-birthday)
  const otherDates = personDates?.filter(d => {
    const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
    return dateType?.toLowerCase() !== 'birthday';
  }) || [];
  
  const handleDelete = async () => {
    if (window.confirm('Are you sure you want to delete this person?')) {
      await deletePerson.mutateAsync(id);
      navigate('/people');
    }
  };
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error || !person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load person.</p>
        <Link to="/people" className="btn-secondary mt-4">Back to People</Link>
      </div>
    );
  }
  
  const acf = person.acf || {};
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to="/people" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to People
        </Link>
        <div className="flex gap-2">
          <Link to={`/people/${id}/edit`} className="btn-secondary">
            <Edit className="w-4 h-4 mr-2" />
            Edit
          </Link>
          <button onClick={handleDelete} className="btn-danger">
            <Trash2 className="w-4 h-4 mr-2" />
            Delete
          </button>
        </div>
      </div>
      
      {/* Profile header */}
      <div className="card p-6">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          {person.thumbnail ? (
            <img
              src={person.thumbnail}
              alt={person.name}
              className="w-24 h-24 rounded-full object-cover"
            />
          ) : (
            <div className="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center">
              <span className="text-3xl font-medium text-gray-500">
                {person.first_name?.[0] || '?'}
              </span>
            </div>
          )}

          <div className="flex-1">
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold">{person.name}</h1>
              {person.is_favorite && (
                <Star className="w-5 h-5 text-yellow-400 fill-current" />
              )}
            </div>
            {acf.nickname && (
              <p className="text-gray-500">"{acf.nickname}"</p>
            )}
            {birthDate ? (
              <Link
                to={`/dates/${birthday.id}/edit`}
                className="text-gray-500 text-sm mt-1 inline-flex items-center hover:text-primary-600 group"
              >
                <Calendar className="w-4 h-4 mr-1" />
                {format(birthDate, 'MMMM d, yyyy')}
                {age !== null && ` (${age} years old)`}
                <Pencil className="w-3 h-3 ml-2 opacity-0 group-hover:opacity-100 transition-opacity" />
              </Link>
            ) : (
              <Link
                to={`/dates/new?person=${id}&type=birthday`}
                className="text-gray-400 text-sm mt-1 inline-flex items-center hover:text-primary-600"
              >
                <Plus className="w-4 h-4 mr-1" />
                Add birthday
              </Link>
            )}
          </div>
        </div>
      </div>
      
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Contact info */}
          {acf.contact_info?.length > 0 && (
            <div className="card p-6">
              <h2 className="font-semibold mb-4">Contact Information</h2>
              <div className="space-y-3">
                {acf.contact_info.map((contact, index) => {
                  const Icon = contact.contact_type === 'email' ? Mail :
                               contact.contact_type === 'phone' || contact.contact_type === 'mobile' ? Phone :
                               contact.contact_type === 'address' ? MapPin :
                               contact.contact_type === 'website' ? Globe : Globe;

                  return (
                    <div key={index} className="flex items-center">
                      <Icon className="w-4 h-4 text-gray-400 mr-3" />
                      <div>
                        <span className="text-sm text-gray-500">{contact.contact_label || contact.contact_type}: </span>
                        <span>{contact.contact_value}</span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Important Dates */}
          {otherDates.length > 0 && (
            <div className="card p-6">
              <h2 className="font-semibold mb-4">Important Dates</h2>
              <div className="space-y-3">
                {otherDates.map((date) => {
                  const dateType = Array.isArray(date.date_type) ? date.date_type[0] : date.date_type;
                  const dateTypeLower = dateType?.toLowerCase() || '';
                  const Icon = dateTypeLower.includes('anniversary') || dateTypeLower.includes('marriage') ? Heart :
                               dateTypeLower.includes('birthday') ? Gift : Calendar;

                  return (
                    <div key={date.id} className="flex items-start group">
                      <div className="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                        <Icon className="w-4 h-4 text-gray-500" />
                      </div>
                      <div className="flex-1">
                        <p className="text-sm font-medium">{date.title}</p>
                        <p className="text-xs text-gray-500">
                          {date.date_value && format(new Date(date.date_value), 'MMMM d, yyyy')}
                        </p>
                        {dateType && (
                          <p className="text-xs text-gray-400 capitalize">{dateType}</p>
                        )}
                      </div>
                      <Link
                        to={`/dates/${date.id}/edit`}
                        className="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-gray-100 rounded"
                      >
                        <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
                      </Link>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Work history */}
          {acf.work_history?.length > 0 && (
            <div className="card p-6">
              <h2 className="font-semibold mb-4">Work History</h2>
              <div className="space-y-4">
                {acf.work_history.map((job, index) => (
                  <div key={index} className="flex items-start">
                    <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                      <Building2 className="w-5 h-5 text-gray-500" />
                    </div>
                    <div>
                      <p className="font-medium">{job.job_title}</p>
                      {job.company && (
                        <Link 
                          to={`/companies/${job.company}`}
                          className="text-sm text-primary-600 hover:underline"
                        >
                          View Company
                        </Link>
                      )}
                      <p className="text-sm text-gray-500">
                        {job.start_date && format(new Date(job.start_date), 'MMM yyyy')}
                        {' - '}
                        {job.is_current ? 'Present' : job.end_date ? format(new Date(job.end_date), 'MMM yyyy') : ''}
                      </p>
                      {job.description && (
                        <p className="text-sm text-gray-600 mt-1">{job.description}</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
          
          {/* Timeline */}
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-semibold">Timeline</h2>
              <button className="btn-secondary text-sm">
                <Plus className="w-4 h-4 mr-1" />
                Add Note
              </button>
            </div>
            
            {timeline?.length > 0 ? (
              <div className="space-y-4">
                {timeline.map((item) => (
                  <div key={item.id} className="border-l-2 border-gray-200 pl-4 pb-4">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                      <span className="capitalize">{item.type}</span>
                      <span>•</span>
                      <span>{format(new Date(item.created), 'MMM d, yyyy')}</span>
                    </div>
                    <p className="mt-1">{item.content}</p>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">
                No notes or activities yet.
              </p>
            )}
          </div>
        </div>
        
        {/* Sidebar */}
        <div className="space-y-6">
          {/* How we met */}
          {(acf.how_we_met || acf.met_date) && (
            <div className="card p-6">
              <h2 className="font-semibold mb-3">How We Met</h2>
              {acf.met_date && (
                <p className="text-sm text-gray-500 mb-2">
                  <Calendar className="w-4 h-4 inline mr-1" />
                  {format(new Date(acf.met_date), 'MMMM d, yyyy')}
                </p>
              )}
              {acf.how_we_met && (
                <p className="text-sm">{acf.how_we_met}</p>
              )}
            </div>
          )}
          
          {/* Relationships */}
          {acf.relationships?.length > 0 && (
            <div className="card p-6">
              <h2 className="font-semibold mb-3">Relationships</h2>
              <div className="space-y-2">
                {acf.relationships.map((rel, index) => (
                  <Link
                    key={index}
                    to={`/people/${rel.related_person}`}
                    className="flex items-center p-2 rounded hover:bg-gray-50"
                  >
                    {rel.person_thumbnail ? (
                      <img
                        src={rel.person_thumbnail}
                        alt={rel.person_name}
                        className="w-8 h-8 rounded-full object-cover mr-2"
                      />
                    ) : (
                      <div className="w-8 h-8 bg-gray-200 rounded-full mr-2 flex items-center justify-center">
                        <span className="text-xs font-medium text-gray-500">
                          {rel.person_name?.[0] || '?'}
                        </span>
                      </div>
                    )}
                    <div>
                      <p className="text-sm font-medium">{rel.person_name || `Person #${rel.related_person}`}</p>
                      <p className="text-xs text-gray-500">{rel.relationship_name || rel.relationship_label}</p>
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
