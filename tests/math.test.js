import { describe, expect, test } from 'vitest';

const sum = (a, b) => a + b;

describe('Math Basics', () => {
    test('adds 1 + 2 to equal 3', () => {
        expect(sum(1, 2)).toBe(3);
    });

    test('fails if sum is wrong', () => {
        expect(sum(1, 2)).not.toBe(4);
    });
});
