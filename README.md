# Event-Driven Content Moderation System

## Quick Start

### Requirements
- **PHP 8.4** with Composer v2
- **Node.js 18+** with npm or bun

### Setup (one-time)
```bash
npm run setup
```

### Run Development Environment! Gemini key must be set in `backend/.env`.
```bash
npm run dev
```

That's it! This starts all services:
- **Vue frontend** (http://localhost:5173)
- **Laravel API** (http://localhost:8000) 
- **WebSocket server** (ws://localhost:3001/ws)
- **Queue worker** for background jobs

## Environment Configuration

The setup command automatically:
- Installs all dependencies (frontend + backend)
- Copies `.env.example` to `.env` if needed
- Generates Laravel app key
- Runs database migrations

**Important**: Add your `GEMINI_API_KEY` to `backend/.env` for content moderation to work.

## Testing
Backend tests can be run with:
```bash
cd backend && php artisan test -p 
```

# Original Requirements

## Overview

Welcome to Hopper HQ's technical assessment! You'll be building an event-driven content moderation system for social media posts. This system should handle asynchronous processing of user-generated content using modern architectural patterns.

**Expected Time**: 3-4 hours (if you need more time, that's fine - just document what you'd improve/add in your README)

## Project Requirements

### Core Functionality

Your system should handle approximately **10,000 posts per day** and include:

1. **Post Submission API**: Accept new social media post content
2. **Event-Driven Processing**: Decouple submission from moderation using events
3. **Content Moderation**: Automatically check posts for inappropriate content
4. **Status Tracking**: Allow users to check moderation status of their posts
5. **User Interface**: Simple frontend for post submission and status tracking

### Technical Requirements

#### Backend
- **Post Submission Endpoint**: Accepts content, generates unique ID, publishes event, returns immediate response
- **Event System**: Use events to trigger moderation (can be file/database queue if message broker setup is complex)
- **Moderation Worker**: Separate component that processes moderation events
- **Third-Party Integration**: Use LLM APIs (Claude, Gemini, or OpenAI) for content analysis
- **Status API**: Endpoints to fetch post status and list all posts with moderation results
- **Tests**: Write tests for your core functionality

#### Frontend
- **Post Submission Form**: Simple interface for users to submit content
- **Status Tracker**: Display submitted posts with current moderation status
- **Real-time Updates**: Update status dynamically (polling or any other method, use what you think is best for this test)

#### Database
Store posts with:
- Unique identifier
- Content
- Current status
- Moderation reason (if applicable)
- Timestamps

## Technology Choice

**Backend**: PHP (Symfony/Laravel)
**Frontend**: Vue.js or React

**You can choose any stack you're comfortable with.** Some suggestions:
**Database**: MySQL, PostgreSQL, SQLite, MongoDB
**Queue/Events**: Redis, RabbitMQ, file-based queue, database queue, or other message brokers

## Evaluation Criteria

You'll be assessed on the following areas:

- Clean, readable, and well-structured code
- Proper error handling and validation
- Appropriate use of design patterns
- Code comments explaining architectural choices
- Event-driven architecture implementation
- Proper separation of concerns
- Database design and API structure
- Fault tolerance
- User-friendly interface
- Proper loading states and error messages
- Clear documentation of assumptions
- Explanation of design decisions
- Test coverage

## Bonus Points
Extra credit for implementing:

- **CQRS Pattern**: Command Query Responsibility Segregation
- **LLM Integration**: Effective use of Claude, Gemini, or OpenAI APIs
- **Docker Setup**: Containerized application for easy deployment
- **Comprehensive Comments**: Detailed explanation of architectural choices

## AI Usage Policy

While you can use AI tools, we prefer **minimal usage** as we want to assess your skills, not AI capabilities. If you do use AI:
- Document where and why you used it
- Justify the decision in your README or code comments
- Ensure you understand and can explain all generated code

## Deliverables

1. **Working Application**: Complete backend and frontend implementation that can be run locally
2. **Setup Instructions**: Clear steps to get your application running locally (database setup, dependencies, environment variables, etc.)
3. **Documentation**: 
   - System architecture explanation
   - Your assumptions about the project
   - Any improvements you'd make with more time
4. **Git Repository**: Clean commit history showing your development process

**Note**: This doesn't need to be production-ready or deployed anywhere. We just need to be able to run it locally following your setup instructions.

## Questions?

If you have any questions about the requirements, make reasonable assumptions and document them in your README.

Good luck! We're excited to see your implementation.