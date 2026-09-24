import { StarRating } from '@/components/storefront/star-rating';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import type { RatingSummary, Review } from '@/types/storefront';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ProductReviewsProps {
    productSlug: string;
    rating: RatingSummary;
    reviews: Review[];
    canReview: boolean;
}

export function ProductReviews({ productSlug, rating, reviews, canReview }: ProductReviewsProps) {
    const isAuthenticated = usePage<SharedData>().props.auth.user != null;

    return (
        <section id="reviews" className="mt-16 scroll-mt-8 border-t pt-10">
            <h2 className="mb-6 text-xl font-semibold">Reviews</h2>

            <div className="grid gap-10 lg:grid-cols-[18rem_1fr]">
                <div>
                    {rating.count === 0 ? (
                        <p className="text-muted-foreground text-sm">No reviews yet.</p>
                    ) : (
                        <>
                            <div className="flex items-baseline gap-2">
                                <span className="text-3xl font-semibold">{rating.average.toFixed(1)}</span>
                                <span className="text-muted-foreground text-sm">out of 5</span>
                            </div>
                            <StarRating value={rating.average} className="mt-1" />
                            <p className="text-muted-foreground mt-1 text-sm">
                                {rating.count} {rating.count === 1 ? 'review' : 'reviews'}
                            </p>

                            <ul className="mt-4 space-y-1">
                                {[5, 4, 3, 2, 1].map((stars) => {
                                    const given = rating.distribution[stars] ?? 0;
                                    const share = rating.count === 0 ? 0 : (given / rating.count) * 100;

                                    return (
                                        <li key={stars} className="flex items-center gap-2 text-sm">
                                            <span className="text-muted-foreground w-10 shrink-0">{stars} ★</span>
                                            <span className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                <span className="block h-full bg-amber-500" style={{ width: `${share}%` }} />
                                            </span>
                                            <span className="text-muted-foreground w-6 text-right">{given}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    )}

                    {canReview ? (
                        <ReviewForm productSlug={productSlug} />
                    ) : (
                        <p className="text-muted-foreground mt-6 text-sm">
                            {isAuthenticated ? (
                                'Thanks - you have already reviewed this product.'
                            ) : (
                                <>
                                    <Link href={route('login')} className="underline underline-offset-4">
                                        Sign in
                                    </Link>{' '}
                                    to write a review.
                                </>
                            )}
                        </p>
                    )}
                </div>

                <div>
                    {reviews.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Be the first to say something about it.</p>
                    ) : (
                        <ul className="space-y-6">
                            {reviews.map((review) => (
                                <li key={review.id} className="border-b pb-6 last:border-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StarRating value={review.rating} size="sm" />
                                        <span className="text-sm font-medium">{review.author_name}</span>
                                        {review.verified_purchase && (
                                            <Badge variant="secondary" className="text-xs">
                                                Verified purchase
                                            </Badge>
                                        )}
                                        <time dateTime={review.published_at} className="text-muted-foreground ml-auto text-xs">
                                            {new Date(review.published_at).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </time>
                                    </div>
                                    <p className="text-muted-foreground mt-2 text-sm leading-relaxed whitespace-pre-line">{review.body}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </section>
    );
}

function ReviewForm({ productSlug }: { productSlug: string }) {
    const [hovered, setHovered] = useState<number | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({ rating: 0, body: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        post(route('products.reviews.store', productSlug), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <form onSubmit={submit} className="mt-8 space-y-4">
            <div>
                <Label className="mb-1.5 block">Your rating</Label>
                {/* Radio inputs, not clickable icons: this is a choice among
                    five, so it should be reachable by keyboard and announced
                    as such. The stars are the visible half of the same thing. */}
                <div className="flex items-center gap-1" onMouseLeave={() => setHovered(null)}>
                    {[1, 2, 3, 4, 5].map((value) => (
                        <label key={value} className="cursor-pointer" onMouseEnter={() => setHovered(value)}>
                            <input
                                type="radio"
                                name="rating"
                                value={value}
                                checked={data.rating === value}
                                onChange={() => setData('rating', value)}
                                className="sr-only"
                            />
                            <Star
                                className={cn(
                                    'size-6 transition-colors',
                                    (hovered ?? data.rating) >= value ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground/40',
                                )}
                                aria-label={`${value} star${value > 1 ? 's' : ''}`}
                            />
                        </label>
                    ))}
                </div>
                {errors.rating && <p className="text-destructive mt-1 text-sm">{errors.rating}</p>}
            </div>

            <div>
                <Label htmlFor="review-body" className="mb-1.5 block">
                    Your review
                </Label>
                <Textarea
                    id="review-body"
                    rows={4}
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    placeholder="What did you think of it?"
                />
                {errors.body && <p className="text-destructive mt-1 text-sm">{errors.body}</p>}
            </div>

            <Button type="submit" disabled={processing || data.rating === 0}>
                Submit review
            </Button>
            <p className="text-muted-foreground text-xs">Reviews are checked before they appear.</p>
        </form>
    );
}
