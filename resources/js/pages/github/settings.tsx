import { Head, useForm } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import GitHubPostSettingController from '@/actions/App/Http/Controllers/GitHubPostSettingController';
import GitHubRepositoryController from '@/actions/App/Http/Controllers/GitHubRepositoryController';
import GitHubPostReviewQueue from '@/components/github-post-review-queue';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { Commit } from '@/types';

type Repository = {
    id: string;
    full_name: string;
    branches: string[];
    webhook_url: string;
    webhook_secret: string;
};

export default function GitHubSettings({
    repositories,
    pendingGitHubPosts,
    requiresApproval,
}: {
    repositories: Repository[];
    pendingGitHubPosts: Commit[];
    requiresApproval: boolean;
}) {
    const approval = useForm({ requires_approval: requiresApproval });
    const createRepository = useForm({ full_name: '', branches: 'main' });

    return (
        <>
            <Head title="GitHub settings" />

            <section className="space-y-4 px-4 py-6 sm:px-6">
                <Heading
                    title="GitHub repositories"
                    description="Add a repository, choose the branches to watch, then add the webhook in that repository’s GitHub settings."
                />

                <div className="rounded-lg border p-4">
                    <h3 className="font-medium">Add a repository</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Enter its owner/name and one branch per line. For
                        example: octocat/hello-world and main.
                    </p>
                    <form
                        className="mt-4 grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createRepository.transform((data) => ({
                                full_name: data.full_name,
                                branches: data.branches
                                    .split(/\r?\n/)
                                    .map((branch) => branch.trim())
                                    .filter(Boolean),
                            }));
                            createRepository.post(
                                GitHubRepositoryController.store().url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => createRepository.reset(),
                                },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="github-repository-name">
                                Repository
                            </Label>
                            <Input
                                id="github-repository-name"
                                value={createRepository.data.full_name}
                                onChange={(event) =>
                                    createRepository.setData(
                                        'full_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="owner/repository"
                                autoComplete="off"
                                className="font-mono"
                                aria-invalid={Boolean(
                                    createRepository.errors.full_name,
                                )}
                            />
                            {createRepository.errors.full_name && (
                                <p className="text-sm text-destructive">
                                    {createRepository.errors.full_name}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="github-repository-branches">
                                Branches to watch
                            </Label>
                            <textarea
                                id="github-repository-branches"
                                value={createRepository.data.branches}
                                onChange={(event) =>
                                    createRepository.setData(
                                        'branches',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                placeholder={'main\nrelease'}
                                disabled={createRepository.processing}
                                aria-invalid={Boolean(
                                    createRepository.errors.branches,
                                )}
                                className="block min-h-20 w-full resize-y overflow-x-hidden rounded-md border bg-transparent px-3 py-2 font-mono text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]"
                            />
                            {createRepository.errors.branches && (
                                <p className="text-sm text-destructive">
                                    {createRepository.errors.branches}
                                </p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                createRepository.processing ||
                                !createRepository.data.full_name.trim() ||
                                !createRepository.data.branches.trim()
                            }
                            className="min-w-36"
                        >
                            {createRepository.processing
                                ? 'Adding…'
                                : 'Add repository'}
                        </Button>
                    </form>
                </div>

                {repositories.length === 0 ? (
                    <p className="rounded-lg border px-4 py-5 text-sm text-muted-foreground">
                        No repositories are being watched.
                    </p>
                ) : (
                    <ul className="space-y-4">
                        {repositories.map((repository) => (
                            <li key={repository.id}>
                                <RepositoryCard repository={repository} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Post approval"
                    description="Choose whether GitHub posts need your review before they appear on dev-null."
                />
                <form
                    className="flex flex-wrap items-center gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        approval.put(GitHubPostSettingController.update().url, {
                            preserveScroll: true,
                            onSuccess: () => approval.setDefaults(),
                        });
                    }}
                >
                    <Checkbox
                        id="github-requires-approval"
                        checked={approval.data.requires_approval}
                        onCheckedChange={(checked) =>
                            approval.setData(
                                'requires_approval',
                                checked === true,
                            )
                        }
                        disabled={approval.processing}
                    />
                    <Label htmlFor="github-requires-approval">
                        Require approval before publishing
                    </Label>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={!approval.isDirty || approval.processing}
                        className="min-w-24"
                    >
                        {approval.processing ? 'Saving…' : 'Save setting'}
                    </Button>
                </form>
            </section>

            <GitHubPostReviewQueue commits={pendingGitHubPosts} />
        </>
    );
}

function RepositoryCard({ repository }: { repository: Repository }) {
    const branchForm = useForm({ branches: repository.branches.join('\n') });
    const deleteForm = useForm({});

    return (
        <article className="space-y-4 rounded-lg border p-4">
            <div className="flex min-h-8 items-center gap-2">
                <Settings2 className="size-4 shrink-0 text-muted-foreground" />
                <h3
                    className="min-w-0 truncate font-mono text-sm"
                    title={repository.full_name}
                >
                    {repository.full_name}
                </h3>
            </div>

            <div className="grid gap-3 rounded-md bg-muted/40 p-3">
                <p className="text-sm">
                    In this GitHub repository, open{' '}
                    <strong>Settings → Webhooks → Add webhook</strong>. Choose
                    JSON and the push event, then copy these values:
                </p>
                <div className="grid gap-2">
                    <Label htmlFor={`webhook-url-${repository.id}`}>
                        Payload URL
                    </Label>
                    <Input
                        id={`webhook-url-${repository.id}`}
                        readOnly
                        value={repository.webhook_url}
                        className="font-mono text-xs"
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`webhook-secret-${repository.id}`}>
                        Secret
                    </Label>
                    <Input
                        id={`webhook-secret-${repository.id}`}
                        readOnly
                        value={repository.webhook_secret}
                        className="font-mono text-xs"
                    />
                    <p className="text-xs text-muted-foreground">
                        This secret is unique to this repository. Keep SSL
                        verification enabled in GitHub.
                    </p>
                </div>
            </div>

            <form
                className="grid gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    branchForm.transform((data) => ({
                        branches: data.branches
                            .split(/\r?\n/)
                            .map((branch) => branch.trim())
                            .filter(Boolean),
                    }));
                    branchForm.put(
                        GitHubRepositoryController.update({
                            repository: repository.id,
                        }).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => branchForm.setDefaults(),
                        },
                    );
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor={`branches-${repository.id}`}>
                        Branches to watch
                    </Label>
                    <textarea
                        id={`branches-${repository.id}`}
                        value={branchForm.data.branches}
                        onChange={(event) =>
                            branchForm.setData('branches', event.target.value)
                        }
                        rows={3}
                        disabled={branchForm.processing}
                        aria-invalid={Boolean(branchForm.errors.branches)}
                        className="block min-h-20 w-full resize-y overflow-x-hidden rounded-md border bg-transparent px-3 py-2 font-mono text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]"
                    />
                    {branchForm.errors.branches && (
                        <p className="text-sm text-destructive">
                            {branchForm.errors.branches}
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={!branchForm.isDirty || branchForm.processing}
                        className="min-w-32"
                    >
                        {branchForm.processing ? 'Saving…' : 'Save branches'}
                    </Button>
                </div>
            </form>

            <div className="border-t pt-3">
                <p className="mb-2 text-xs text-muted-foreground">
                    Before removing this repo here, delete its webhook in GitHub
                    settings. That stops GitHub retrying deliveries to this
                    unique URL.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={deleteForm.processing}
                    className="min-w-32"
                    onClick={() =>
                        deleteForm.delete(
                            GitHubRepositoryController.destroy({
                                repository: repository.id,
                            }).url,
                            { preserveScroll: true },
                        )
                    }
                >
                    {deleteForm.processing ? 'Removing…' : 'Remove repository'}
                </Button>
            </div>
        </article>
    );
}

GitHubSettings.layout = [AppLayout, SettingsLayout];
