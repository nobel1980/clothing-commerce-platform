'use client';

import * as React from 'react';
import { ChevronLeft, ChevronRight, Maximize2 } from 'lucide-react';
import type { ProductImageItem } from '@clothing-commerce/types';
import { Button } from '@/components/ui/button';

export interface ProductImageGalleryProps {
  images: ProductImageItem[];
  productName: string;
}

export function ProductImageGallery({
  images,
  productName,
}: ProductImageGalleryProps) {
  const sortedImages = React.useMemo(
    () =>
      [...images].sort((a, b) => {
        if (a.is_primary !== b.is_primary) {
          return a.is_primary ? -1 : 1;
        }
        return a.sort_order - b.sort_order;
      }),
    [images]
  );

  const [activeIndex, setActiveIndex] = React.useState(0);
  const [isZoomed, setIsZoomed] = React.useState(false);

  const total = sortedImages.length;
  const activeImage = sortedImages[activeIndex] ?? null;

  const handlePrev = React.useCallback(() => {
    if (total <= 1) return;
    setActiveIndex((prev) => (prev - 1 + total) % total);
  }, [total]);

  const handleNext = React.useCallback(() => {
    if (total <= 1) return;
    setActiveIndex((prev) => (prev + 1) % total);
  }, [total]);

  const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      handlePrev();
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      handleNext();
    }
  };

  return (
    <section
      aria-label={`${productName} image gallery`}
      onKeyDown={handleKeyDown}
      tabIndex={0}
      className="flex flex-col gap-4"
    >
      {/* Main image stage */}
      <div
        style={{
          position: 'relative',
          width: '100%',
          aspectRatio: '4 / 5',
          backgroundColor: '#f4f3ef',
          borderRadius: '0.875rem',
          border: '1px solid #e5e2dc',
          overflow: 'hidden',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        {activeImage ? (
          <img
            src={activeImage.url}
            alt={activeImage.alt_text || `${productName} - view ${activeIndex + 1}`}
            data-testid="gallery-active-image"
            style={{
              width: '100%',
              height: '100%',
              objectFit: 'cover',
              transform: isZoomed ? 'scale(1.25)' : 'scale(1)',
              transition: 'transform 0.25s ease',
              cursor: isZoomed ? 'zoom-out' : 'zoom-in',
            }}
            onClick={() => setIsZoomed((z) => !z)}
          />
        ) : (
          <div className="p-8 text-center">
            <p className="text-xs uppercase tracking-wider text-muted">Atelier Archive</p>
            <p className="mt-1 text-lg font-semibold">{productName}</p>
          </div>
        )}

        {activeImage && (
          <Button
            type="button"
            variant="secondary"
            size="sm"
            aria-label={isZoomed ? 'Reset image zoom' : 'Zoom product image'}
            aria-pressed={isZoomed}
            onClick={() => setIsZoomed((z) => !z)}
            style={{
              position: 'absolute',
              top: '0.875rem',
              right: '0.875rem',
            }}
          >
            <Maximize2 size={14} />
            {isZoomed ? 'Reset' : 'Zoom'}
          </Button>
        )}

        {total > 1 && (
          <>
            <Button
              type="button"
              variant="secondary"
              size="icon"
              aria-label="Previous product image"
              onClick={handlePrev}
              style={{
                position: 'absolute',
                left: '0.875rem',
                top: '50%',
                transform: 'translateY(-50%)',
                borderRadius: '9999px',
              }}
            >
              <ChevronLeft size={18} />
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="icon"
              aria-label="Next product image"
              onClick={handleNext}
              style={{
                position: 'absolute',
                right: '0.875rem',
                top: '50%',
                transform: 'translateY(-50%)',
                borderRadius: '9999px',
              }}
            >
              <ChevronRight size={18} />
            </Button>
            <span
              aria-live="polite"
              className="rounded-full bg-surface px-3 py-1 text-xs font-medium"
              style={{
                position: 'absolute',
                bottom: '0.875rem',
                right: '0.875rem',
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                padding: '0.25rem 0.625rem',
              }}
            >
              {activeIndex + 1} / {total}
            </span>
          </>
        )}
      </div>

      {/* Thumbnail strip */}
      {total > 1 && (
        <div
          role="tablist"
          aria-label="Product image thumbnails"
          className="flex flex-wrap gap-3"
        >
          {sortedImages.map((img, idx) => {
            const isSelected = idx === activeIndex;
            return (
              <button
                key={img.id}
                type="button"
                role="tab"
                aria-selected={isSelected}
                aria-label={`View image ${idx + 1} of ${total}`}
                onClick={() => {
                  setActiveIndex(idx);
                  setIsZoomed(false);
                }}
                style={{
                  width: '4.75rem',
                  height: '6rem',
                  borderRadius: '0.5rem',
                  overflow: 'hidden',
                  border: isSelected ? '2px solid #18181b' : '1px solid #d6d3cd',
                  padding: 0,
                  background: '#f4f3ef',
                  cursor: 'pointer',
                  opacity: isSelected ? 1 : 0.72,
                }}
              >
                <img
                  src={img.url}
                  alt={img.alt_text || `${productName} thumbnail ${idx + 1}`}
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
              </button>
            );
          })}
        </div>
      )}
    </section>
  );
}

