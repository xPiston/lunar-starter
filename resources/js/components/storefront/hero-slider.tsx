import { Carousel, CarouselApi, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { cn } from '@/lib/utils';
import type { HeroSlide } from '@/types/storefront';
import { useEffect, useState } from 'react';

const AUTOPLAY_INTERVAL = 6000;

export function HeroSlider({ slides }: { slides: HeroSlide[] }) {
    const [api, setApi] = useState<CarouselApi>();
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        if (!api) {
            return;
        }

        setCurrent(api.selectedScrollSnap());
        api.on('select', () => setCurrent(api.selectedScrollSnap()));
    }, [api]);

    // Auto-advance, unless the visitor has asked the system for less motion -
    // a carousel that moves on its own is exactly what that setting is about.
    // Interacting with the slider stops it for good: having a slide change
    // under someone who is reading it is worse than not advancing at all.
    useEffect(() => {
        if (!api || slides.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const timer = window.setInterval(() => api.scrollNext(), AUTOPLAY_INTERVAL);
        api.on('pointerDown', () => window.clearInterval(timer));

        return () => window.clearInterval(timer);
    }, [api, slides.length]);

    return (
        <section className="mb-10" aria-roledescription="carousel" aria-label="Featured">
            <Carousel setApi={setApi} opts={{ loop: slides.length > 1 }}>
                <CarouselContent>
                    {slides.map((slide) => (
                        <CarouselItem key={slide.id}>
                            <SlideContent slide={slide} />
                        </CarouselItem>
                    ))}
                </CarouselContent>

                {/* Arrows from `sm` up only: they sit on top of the slide, and
                    at phone width they land on the heading. Swiping and the
                    dots below cover the same ground where they're hidden. */}
                {slides.length > 1 && (
                    <>
                        <CarouselPrevious className="left-4 hidden sm:flex" />
                        <CarouselNext className="right-4 hidden sm:flex" />
                    </>
                )}
            </Carousel>

            {slides.length > 1 && (
                <div className="mt-4 flex justify-center gap-2">
                    {slides.map((slide, index) => (
                        <button
                            key={slide.id}
                            type="button"
                            onClick={() => api?.scrollTo(index)}
                            aria-label={`Go to slide ${index + 1}`}
                            aria-current={index === current}
                            className={cn(
                                'h-2 rounded-full transition-all',
                                index === current ? 'bg-foreground w-6' : 'bg-muted-foreground/40 hover:bg-muted-foreground w-2',
                            )}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function SlideContent({ slide }: { slide: HeroSlide }) {
    const content = (
        <div className="relative aspect-[5/2] overflow-hidden rounded-2xl border">
            <img src={slide.image_url} alt="" className="h-full w-full object-cover" />

            {/* The image is decorative - the heading below carries the meaning,
                so alt is empty rather than a duplicate of the title. */}
            <div className="absolute inset-0 bg-gradient-to-r from-black/70 via-black/40 to-transparent" />

            <div className="absolute inset-0 flex flex-col justify-center gap-1 p-5 sm:gap-2 sm:p-12">
                <h2 className="max-w-[80%] text-lg leading-tight font-semibold text-white sm:max-w-md sm:text-4xl">{slide.title}</h2>
                {slide.subtitle && <p className="max-w-[80%] text-xs text-white/80 sm:max-w-md sm:text-base">{slide.subtitle}</p>}
            </div>
        </div>
    );

    // A plain anchor, not an Inertia Link: the back office accepts any URL,
    // including one pointing outside this app, and Link would try to fetch it
    // as an Inertia page.
    return slide.link_url ? (
        <a href={slide.link_url} className="focus-visible:ring-ring block rounded-2xl focus-visible:ring-2 focus-visible:outline-none">
            {content}
        </a>
    ) : (
        content
    );
}
