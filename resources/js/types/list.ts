/**
 * Shared list contract with the backend ListQuery
 * (app/Domain/Shared/Support/Listing/ListQuery.php).
 */

export type ListMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type DateRangeValue = { from?: string; to?: string };

export type FilterValue = string | string[] | boolean | DateRangeValue;

export type ListQueryState = {
    /** Applied (normalized) filters; the server sends [] when empty. */
    filters: Record<string, FilterValue> | [];
    sort: string | null;
    per_page: number;
};

export type ListResult<T> = {
    data: T[];
    meta: ListMeta;
    query: ListQueryState;
};

export type FilterOption = { value: string; label: string };

type FilterBase = { key: string; label: string };

export type FilterDefinition =
    | (FilterBase & { type: 'text'; placeholder?: string })
    | (FilterBase & {
          type: 'select';
          options: FilterOption[];
          multiple?: boolean;
      })
    | (FilterBase & {
          type: 'boolean';
          trueLabel?: string;
          falseLabel?: string;
      })
    | (FilterBase & { type: 'date' })
    | (FilterBase & {
          type: 'entity';
          entity: string;
          params?: Record<string, string | number | null | undefined>;
      });

/** Item returned by GET /lookups/{entity}. */
export type LookupItem = {
    id: number | string;
    label: string;
    description: string | null;
};
