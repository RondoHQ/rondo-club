/** The API caps pages at 100; a CSV must contain every matching page or fail. */
export async function fetchAllPeoplePages(getPage, filters = {}) {
  const params = { ...filters, page: 1, per_page: 100 };
  const first = (await getPage(params)).data;
  const totalPages = first.total_pages || 1;
  if (totalPages <= 1) return first.people || [];
  const rest = await Promise.all(
    Array.from({ length: totalPages - 1 }, (_, index) => index + 2).map(async page => {
      const response = await getPage({ ...params, page });
      return response.data.people || [];
    })
  );
  return [...(first.people || []), ...rest.flat()];
}
