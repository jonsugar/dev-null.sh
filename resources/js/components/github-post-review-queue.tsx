import { router, useForm } from '@inertiajs/react';
import { Check, ExternalLink, X } from 'lucide-react';
import CommitApprovalController from '@/actions/App/Http/Controllers/CommitApprovalController';
import CommitRejectionController from '@/actions/App/Http/Controllers/CommitRejectionController';
import GitHubPostController from '@/actions/App/Http/Controllers/GitHubPostController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Commit } from '@/types';

export default function GitHubPostReviewQueue({
    commits,
}: {
    commits: Commit[];
}) {
    return (
        <section
            className="px-4 py-6 sm:px-6"
            aria-label="GitHub posts for review"
        >
            <Heading
                title="GitHub posts for review"
                description="Edit each post before you approve it. Rejected posts stay private."
            />
            {commits.length === 0 ? (
                <p className="rounded-lg border px-4 py-5 text-sm text-muted-foreground">
                    No GitHub posts need review.
                </p>
            ) : (
                <ul className="space-y-4">
                    {commits.map((commit) => (
                        <li key={commit.id}>
                            <ReviewCard commit={commit} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function ReviewCard({ commit }: { commit: Commit }) {
    const form = useForm({ body: commit.body });

    const moderate = (url: string) => {
        router.visit(url, {
            method: 'post',
            data: { body: form.data.body },
            preserveScroll: true,
        });
    };

    return (
        <article className="rounded-lg border p-4">
            <div className="mb-3 flex min-h-7 items-center justify-between gap-3">
                <p
                    className="min-w-0 truncate font-mono text-sm"
                    title={commit.source_url ?? undefined}
                >
                    {commit.source_url}
                </p>
                {commit.source_url && (
                    <a
                        href={commit.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="shrink-0 text-muted-foreground hover:text-foreground"
                        aria-label="Open GitHub push"
                    >
                        <ExternalLink className="size-4" />
                    </a>
                )}
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(GitHubPostController.update({ commit }).url, {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
            >
                <Label htmlFor={`github-post-${commit.id}`}>Post text</Label>
                <textarea
                    id={`github-post-${commit.id}`}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    maxLength={280}
                    rows={4}
                    disabled={form.processing}
                    aria-invalid={Boolean(form.errors.body)}
                    aria-describedby={`github-post-help-${commit.id}`}
                    className="mt-2 block min-h-24 w-full resize-y overflow-x-hidden rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]"
                />
                <div
                    id={`github-post-help-${commit.id}`}
                    className="mt-1 flex justify-between text-xs text-muted-foreground"
                >
                    <span>{form.errors.body}</span>
                    <span className="font-mono tabular-nums">
                        {form.data.body.length}/280
                    </span>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={!form.isDirty || form.processing}
                        className="min-w-28"
                    >
                        {form.processing ? 'Saving…' : 'Save edits'}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        disabled={
                            form.processing || form.data.body.trim().length < 3
                        }
                        className="min-w-28"
                        onClick={() =>
                            moderate(
                                CommitApprovalController.store({ commit }).url,
                            )
                        }
                    >
                        <Check />
                        Approve
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={form.processing}
                        className="min-w-28"
                        onClick={() =>
                            moderate(
                                CommitRejectionController.store({ commit }).url,
                            )
                        }
                    >
                        <X />
                        Reject
                    </Button>
                </div>
            </form>
        </article>
    );
}
