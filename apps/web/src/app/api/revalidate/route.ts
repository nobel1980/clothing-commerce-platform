import { timingSafeEqual } from 'node:crypto';
import { revalidatePath, revalidateTag } from 'next/cache';
import { NextResponse, type NextRequest } from 'next/server';

function secretsMatch(expected: string, provided: string): boolean {
  const a = Buffer.from(expected);
  const b = Buffer.from(provided);
  return a.length === b.length && timingSafeEqual(a, b);
}

export async function POST(request: NextRequest) {
  const configuredSecret = process.env.REVALIDATION_SECRET;
  const providedSecret = request.headers.get('x-revalidation-secret') ?? '';

  // Fail closed: without a configured secret nobody may trigger cache invalidation.
  if (!configuredSecret) {
    return NextResponse.json(
      { revalidated: false, message: 'Revalidation is not configured.' },
      { status: 503 }
    );
  }

  if (!secretsMatch(configuredSecret, providedSecret)) {
    return NextResponse.json(
      { revalidated: false, message: 'Invalid revalidation token.' },
      { status: 401 }
    );
  }

  let body: { tags?: string[]; paths?: string[]; slug?: string } = {};
  try {
    body = (await request.json()) as { tags?: string[]; paths?: string[]; slug?: string };
  } catch {
    body = {};
  }

  const tagsToRevalidate = new Set<string>(body.tags ?? ['catalog', 'products', 'categories']);
  if (body.slug) {
    tagsToRevalidate.add(`product-${body.slug}`);
    tagsToRevalidate.add(`category-${body.slug}`);
  }

  for (const tag of tagsToRevalidate) {
    revalidateTag(tag, 'max');
  }

  const pathsToRevalidate = body.paths ?? ['/', '/products', '/categories'];
  for (const path of pathsToRevalidate) {
    revalidatePath(path);
  }

  return NextResponse.json({
    revalidated: true,
    tags: Array.from(tagsToRevalidate),
    paths: pathsToRevalidate,
    timestamp: new Date().toISOString(),
  });
}

