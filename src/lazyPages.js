import { lazy } from 'react';

// Lazy-loaded page components
export const PeopleList = lazy(() => import('@/pages/People/PeopleList'));
export const PeopleAnniversaries = lazy(() => import('@/pages/People/PeopleAnniversaries'));
export const PersonDetail = lazy(() => import('@/pages/People/PersonDetail'));
export const TeamsList = lazy(() => import('@/pages/Teams/TeamsList'));
export const TeamDetail = lazy(() => import('@/pages/Teams/TeamDetail'));
export const CommissiesList = lazy(() => import('@/pages/Commissies/CommissiesList'));
export const CommissieDetail = lazy(() => import('@/pages/Commissies/CommissieDetail'));
export const TodosList = lazy(() => import('@/pages/Todos/TodosList'));
export const FeedbackList = lazy(() => import('@/pages/Feedback/FeedbackList'));
export const FeedbackDetail = lazy(() => import('@/pages/Feedback/FeedbackDetail'));
export const Settings = lazy(() => import('@/pages/Settings/Settings'));
export const VOG = lazy(() => import('@/pages/VOG/VOG'));
export const Contributie = lazy(() => import('@/pages/Contributie/Contributie'));
export const DisciplineCasesList = lazy(() => import('@/pages/DisciplineCases/DisciplineCasesList'));
export const FinanceSettings = lazy(() => import('@/pages/Finance/FinanceSettings'));
export const FinanceDashboard = lazy(() => import('@/pages/Finance/FinanceDashboard'));
export const Facturen = lazy(() => import('@/pages/Finance/Facturen'));
export const FactuurDetail = lazy(() => import('@/pages/Finance/FactuurDetail'));
export const RelationshipTypes = lazy(() => import('@/pages/Settings/RelationshipTypes'));
export const CustomFields = lazy(() => import('@/pages/Settings/CustomFields'));
export const FeedbackManagement = lazy(() => import('@/pages/Settings/FeedbackManagement'));
export const Login = lazy(() => import('@/pages/Login'));
export const Profile = lazy(() => import('@/pages/Profile/Profile'));
export const MembershipPassScanner = lazy(() => import('@/pages/MembershipPassScanner'));
export const ClothingPage = lazy(() => import('@/pages/Clothing/ClothingPage'));
