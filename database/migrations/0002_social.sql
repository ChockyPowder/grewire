BEGIN;

CREATE TABLE IF NOT EXISTS friendships (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    friend_user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(16) NOT NULL DEFAULT 'accepted'
        CHECK (status IN ('pending','accepted','blocked')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (user_id <> friend_user_id),
    UNIQUE (user_id, friend_user_id)
);

CREATE INDEX IF NOT EXISTS idx_friendships_user_status
    ON friendships(user_id, status);

CREATE INDEX IF NOT EXISTS idx_friendships_friend_status
    ON friendships(friend_user_id, status);

COMMIT;
